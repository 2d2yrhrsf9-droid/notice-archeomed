# Formulaire des notices d’archéologie médiévale — version 3.107

Formulaire de soumission des notices d'opération pour la Chronique
d'*Archéologie médiévale*. La rédaction reçoit le courriel habituel,
accompagné d'un fichier **DOCX** portant la feuille de styles Métopes (chaîne d'édition XML créée par le Pôle document numérique et l'infrastructure Métopes de l'université de Caen Normandie, https://www.metopes.fr),
prêt à être relu puis versé dans la chaîne XML.

Si l'extension PHP ZipArchive est absente de l'hébergement, le plugin
produit automatiquement un RTF équivalent : les deux formats appliquent
exactement les mêmes styles.

Shortcode : `[notice_archeomed_pactols]`

## Installation

1. Extensions > Ajouter > Téléverser une extension
2. Choisir `notice-archeomed.zip`, installer, puis activer.

## Mettre à jour — sans rien supprimer

Téléverse simplement la nouvelle archive au même endroit. WordPress reconnaît
une extension déjà installée, affiche les deux versions côte à côte et propose
**« Remplacer l'actuelle par celle téléversée »**. C'est cette voie qu'il faut
prendre.

**Ne supprime pas l'extension pour la réinstaller.** Les notices reçues ne
disparaîtraient pas — rien dans ce plugin n'efface de données, et il n'a pas
de crochet de désinstallation — mais elles cesseraient d'être visibles le
temps que l'extension soit absente, puisque c'est elle qui déclare leur type
de contenu. Une manœuvre inutilement inquiétante.

Les réglages et les notices vivent dans la base, jamais dans les fichiers de
l'extension : une mise à jour ne les touche pas.

**La feuille de styles de référence est `modele-metopes.docx`.** C'est elle
que le plugin emploie, et le document joint à chaque notice est un DOCX.

Le plugin refuse de s'activer si ce gabarit manque : sans lui, aucun document
ne peut être mis en forme.

`modele-metopes.rtf` en est le pendant dans l'autre format — mêmes styles,
mêmes noms. Il ne sert que sur un hébergement dépourvu de l'extension
ZipArchive, sans laquelle un DOCX, qui est une archive zip, ne peut pas se
fabriquer. Son absence ne se signale donc que là où il servirait.

## La protection anti-robot

Trois choix dans **Réglages ▸ Notice Archéomed**, et le premier est celui par
défaut :

| Choix | Ce que ça suppose |
|---|---|
| **Pièce de puzzle à glisser** | rien : une pièce à mener dans son encoche, vérifiée sur place |
| Cloudflare Turnstile seul | que **le serveur** puisse joindre `challenges.cloudflare.com` |
| Les deux | idem, en plus de la pièce |

La vérification a d'abord été une addition en toutes lettres, puis un curseur
à mener au bout de sa course. Le curseur s'appuyait sur un `input type=range` :
**un thème qui remet l'apparence des champs à zéro lui ôte sa pastille**, et
il ne restait qu'à cliquer la piste de proche en proche pour avancer. La pièce
de puzzle est donc dessinée de bout en bout sur deux `canvas`, et se déplace
sur des événements pointeur — souris, doigt et stylet d'une seule écriture —
sur quoi aucune feuille de style n'a prise.

Elle ne protège ni mieux ni moins bien que ce qu'elle remplace : la position
de l'encoche est dans la page, comme la réponse de l'addition y était. Ce qui
protège vraiment est ailleurs : Turnstile quand le proxy le laisse passer, le
champ-piège, le délai minimal de trois secondes et les plafonds horaires.

**Au clavier**, la poignée se rejoint par la tabulation, se déplace aux
flèches — de dix crans la touche Majuscule enfoncée — et se valide par Entrée.
La poignée annonce sa position et celle de l'encoche
(`aria-valuetext`) : un lecteur d'écran peut donc la placer à l'oreille, sans
voir le dessin, au prix de plusieurs frappes. La vérification demande un geste précis ; le
formulaire donne, dans son introduction, l'adresse où envoyer la notice par
courriel quand il fait obstacle, et Turnstile sait s'en passer.

**Le sceau porte l'heure d'affichage, et ne sert qu'une fois.** Il ne portait
que la réponse : un couple (réponse, sceau) récolté une fois valait
indéfiniment, sur toutes les pages. Il porte maintenant l'heure d'affichage de
la page (`na_ts`), qu'on ne peut donc plus changer sans le défaire, et vaut
vingt-quatre heures (`PUZZLE_VALIDITE`) : la limite de temps du RGAA (critère
13.1) veut vingt heures au moins, et un formulaire ouvert le soir doit pouvoir
partir le lendemain. Un dépôt réussi retient son sceau : récolté, il ne se
rejoue pas. Rien d'autre n'est retenu entre l'affichage et l'envoi.

Quand Cloudflare est injoignable, Turnstile laisse passer : le formulaire
ajoute alors la pièce de puzzle à Turnstile, le temps que la sortie revienne
(un jour après le dernier échec).

**Les autres garde-fous du serveur.** Le jeton du formulaire (`nonce`) vit de
24 à 48 heures, pour ce seul formulaire, et non les 12 à 24 de WordPress : sans
cela, le puzzle valable 24 h n'aurait servi à rien. L'heure d'affichage de la
page (`na_ts`) est exigée — absente, dans le futur, vieille d'une semaine ou de
moins de trois secondes, l'envoi est refusé. Le plafond de tentatives compte les
refus, et passe avant le contrôle du jeton. Un plafond global de 100 dépôts par
heure, toutes adresses confondues, tient quand la vérification anti-robot laisse
passer (Turnstile ouvert faute de Cloudflare). L'adresse du visiteur est
`REMOTE_ADDR`, sauf derrière des relais déclarés dans `NA_PROXYS` (wp-config).
La page n'est jamais servie d'un cache (`DONOTCACHEPAGE`, en-têtes de non-cache).
Voir la section « Exploitation » de `doc/notice-technique.md`.

Turnstile n'est pas une protection si le serveur ne joint pas Cloudflare.
Derrière un proxy filtrant — hébergement institutionnel — la vérification
échoue, le plugin laisse passer pour ne pas perdre de notice, et il ne reste
qu'une case décorative. L'erreur à reconnaître, dans le test de la page de
réglages ou dans le journal :

```
cURL error 56: Received HTTP code 403 from proxy after CONNECT
```

Elle veut dire : demander à l'hébergeur d'ouvrir le flux sortant, et en
attendant, s'en tenir à la protection locale.

### Suspendre la vérification sur un site d'essai

Un dépôt complet ne s'essaie pas automatiquement tant que la pièce de puzzle
est là : elle est faite pour arrêter ce qui n'est pas une personne. Sur un
**site d'essai seulement**, on peut la suspendre dans `wp-config.php` :

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );   // ou 'development'
define( 'NA_PROTECTION', 'aucune' );
```

La valeur `aucune` n'existe pas dans la page de réglages, et elle est
ignorée sur un site qui se déclare en production — ce que WordPress fait par
défaut. Un bandeau le rappelle sur toutes les pages de l'administration tant
qu'elle est active. Le champ-piège, le délai minimal et les plafonds d'envoi
restent en place.

## Les mises à jour depuis GitHub

Le plugin ne vit pas dans le répertoire de WordPress : sans mécanisme, chaque
correction se téléverse à la main. Renseignez le dépôt dans **Réglages ▸
Mises à jour**, et les nouvelles versions paraissent dans **Extensions**
comme pour n'importe quelle autre.

Deux choses ne se devinent pas.

**WordPress compare des numéros de version, pas des commits.** Une correction
poussée sans monter le `Version:` de l'en-tête reste invisible — et c'est
heureux : on ne veut pas qu'un travail en cours s'annonce comme une mise à
jour sur le site de la revue. Le cycle est donc : commiter, monter le numéro,
publier une release.

**L'archive de la release doit avoir la bonne racine.** Celle que GitHub
fabrique tout seul s'ouvre sur `depot-3.16/` là où WordPress attend
`notice-archeomed/` : le plugin s'installerait à côté de lui-même au lieu
de se mettre à jour. Il faut donc **joindre à la release l'archive produite
par `./empaqueter`**. Le plugin la cherche par son nom, `notice-archeomed.zip`,
et **refuse d'annoncer une mise à jour** s'il ne la trouve pas : mieux vaut
un silence qu'une installation cassée. La page de réglages le dit en rouge.

Le dépôt s'écrit `compte/depot`. Un jeton n'est nécessaire que s'il est
privé ; il n'est envoyé qu'aux hôtes de GitHub, jamais ailleurs.

GitHub n'est interrogé qu'une fois toutes les six heures : WordPress vérifie
les mises à jour souvent, et sans cette réserve le site appellerait l'API des
dizaines de fois par jour pour une réponse qui ne change pas. Enregistrer les
réglages vide la réserve, de quoi voir une release sans attendre.

## La sortie vers GitHub

Le même proxy déciderait du sort d'une mise à jour servie depuis GitHub —
celle qui paraîtrait dans « Extensions » au lieu d'un téléversement à la
main. Un bouton des réglages le dit, sans rien installer :

**Réglages ▸ Sortie vers GitHub ▸ Tester l'accès à GitHub.**

Il interroge deux hôtes, parce que la mise à jour tient en deux étapes :
`api.github.com` annonce quelle version existe, `objects.githubusercontent.com`
sert le fichier. Un proxy peut laisser passer l'un et bloquer l'autre, et la
mise à jour s'arrêterait au milieu — le site saurait qu'une version existe
sans pouvoir la prendre.

**Ce qui compte n'est pas le code HTTP mais le fait d'en recevoir un.** L'API
répond 200, l'hôte des fichiers répond 404 faute d'URL signée : les deux
prouvent qu'on a traversé. Seule une erreur de transport signe le blocage, et
le test nomme le proxy quand il la reconnaît.

À savoir si le test passe : WordPress ne compare pas des commits mais des
**numéros de version**. Il faudrait donc monter le `Version:` de l'en-tête et
publier une release portant **l'archive produite par `./empaqueter`** — celle
que GitHub fabrique tout seul a pour racine `notice-archeomed-<version>/` là
où WordPress attend `notice-archeomed/`, et le plugin s'installerait à
côté de lui-même au lieu de se mettre à jour.

La question tirée au sort est scellée par une empreinte déposée dans le
formulaire, non retenue sur le serveur : une page mise en cache garde donc sa
question et reste valable. La réponse s'écrit en chiffres ou en toutes
lettres — refuser « sept » parce qu'on attendait « 7 » serait une brimade.

## Réglage indispensable avant la mise en service

Après activation, aller dans **Chronique ▸ Réglages**, onglet
**Destinataires**. La liste des destinataires **n'a pas de valeur par
défaut**. Tant que personne n'y reçoit les notices, les dépôts sont conservés
en file mais aucun ne part, et un bandeau rouge le signale sur toutes les
pages de l'administration, et la fiche d'une notice propose de renseigner
l'adresse plutôt que de relancer un envoi qui échouerait. C'est donc le
premier réglage à faire après l'installation.

Si l'on choisit Cloudflare Turnstile dans l'onglet **Formulaire**, il faut
aussi y coller sa clé secrète. Le bouton « Tester Cloudflare », visible quelle
que soit la protection, dit si le serveur joint Cloudflare (un proxy peut
l'empêcher) et, si une clé est enregistrée, si Cloudflare la reconnaît ; il
n'envoie aucune notice. La pièce de puzzle, choisie par défaut, ne demande rien.

Le même onglet ouvre un **essai de charge**, pour éprouver le formulaire avec
des dépôts automatiques, à plusieurs en même temps. Ouvert pour deux jours au
plus, il donne une clé : un dépôt qui la présente (champ `na_essai`) passe
sans pièce de puzzle ni Turnstile, et sans plafond par poste ni par adresse.
Le plafond de toute la revue le borne sans qu'il s'y compte, et la garde du
disque reste en place. La réponse d'un tel dépôt porte son lien de correction
(en-tête `X-NA-Correction`). Ses notices sont marquées « Essai » dans la liste,
n'entrent pas dans l'annuaire, et un bouton les met toutes à la corbeille ;
leurs dossiers sur sDrive se suppriment là-bas.

Les réglages sont rangés en onglets : Destinataires, Numéro et iconographie,
Normes éditoriales, Styles Métopes, Formulaire, Accessibilité, Candidats à
Pactols, Relecture, Courriel (avec le rythme d'envoi), Feuille de styles,
Mises à jour, Hébergement, Stockage, Annuaire, Documentation. Chacun
s'enregistre pour son compte.

L'onglet **Courriel** règle le **nom de l'expéditeur** : « Revue Archéologie
médiévale » par défaut, au lieu du nom que l'hébergement donne à tout ce que
le site envoie. Il ne vaut que pour les courriels du formulaire, et l'adresse
d'expédition ne change pas. Le serveur de courriel de l'hébergement peut
encore réécrire ce nom : s'il le fait, c'est à lui qu'il faut le demander.

## Qui reçoit quoi

Deux sortes de courriels partent du plugin, et l'on choisit destinataire par
destinataire :

| Sorte | Quand | Ce qu'il porte |
|---|---|---|
| **Notice** | à chaque dépôt | le texte mis en forme, le document stylé Métopes, les illustrations |
| **Récapitulatif** | une fois par jour | la liste des notices reçues, rangées par rubrique, chacune liée à sa fiche |

Une rédaction n'est pas une personne : le secrétariat veut chaque notice à
mesure qu'elle arrive, la direction veut la liste du jour et non quarante
courriels. Chaque ligne du tableau porte donc une adresse et deux cases. Le
bouton « + Ajouter un destinataire » en ajoute une, la croix la retire, et
une adresse effacée disparaît à l'enregistrement. Dix destinataires au plus.

Les notices partent en un seul courriel adressé à tous ceux qui les
reçoivent : il pèse jusqu'au poids réglé (dix méga-octets par défaut), et le
répéter par destinataire ferait payer la liste au poids. Les destinataires se
voient donc les uns les autres, ce qui est le cas d'une rédaction.

**Ce que porte le courriel d'une notice.** Le titre donne la commune en
capitales, comme la notice imprimée. Depuis la version 3.81, l'aperçu de
chaque figure (mille pixels, cent cinquante Ko au plus) s'affiche **dans le
corps du message, au-dessus de sa légende**, et non plus en pièce jointe à
la fin ; le titre de premier niveau de l'aperçu est en gras, sans les
capitales du document. Partent aussi le Word et les autorisations de
reproduction. Les originaux ne partent que si la figure n'a pas d'aperçu :
ils sont sur la fiche, ou sur sDrive. Ce qui ne tient pas dans le poids
réglé reste sur le site, et le courriel le dit. Les notices déjà en file
avant la mise à jour gardent leurs figures en pièces jointes.

**Les copies aux auteurs.** Chaque adresse de la notice reçoit sa copie :
le responsable, et selon les cas le co-responsable, les co-auteurs et qui
dépose pour le responsable. Qui dépose laisse depuis la 3.81 son nom et son prénom, en plus de
son adresse ; le courriel de la rédaction les donne à la ligne « Déposée
par ». La fiche de la notice dit, sous **Copies aux auteurs**, à quelles
adresses la copie est partie au dernier envoi et ce que le serveur de
courriel a répondu pour chacune — réservé à qui règle l'extension. Une copie
acceptée peut encore finir dans les indésirables du destinataire. Dans la
file, un lien « Écrire aux N adresses de la notice » écrit d'un coup au
responsable, au co-responsable, aux co-auteurs et au déposant.

**Reprise de l'ancien réglage.** Les installations qui n'avaient qu'une
adresse unique la retrouvent en tête de liste, cochée pour les deux sortes :
la mise à jour n'interrompt aucun envoi, même si l'on n'ouvre pas la page.

**La clé qui figurait dans la version 1 est à considérer comme compromise :
elle circulait en clair dans le fichier PHP. Il faut en générer une nouvelle
depuis le tableau de bord Cloudflare et n'utiliser que celle-là.**

### Variante par wp-config.php

Si l'accès aux fichiers est possible et qu'on préfère tenir ces valeurs hors
de la base de données, les constantes suivantes sont prioritaires sur la page
de réglages :

```php
define( 'NA_TURNSTILE_SECRET', 'votre_cle_secrete' );
define( 'NA_TURNSTILE_SITE',   'votre_cle_de_site' );
define( 'NA_DEST_EMAIL',       'adresse@exemple.fr, autre@exemple.fr' );
```

Les champs correspondants apparaissent alors verrouillés dans l'interface,
pour éviter toute ambiguïté sur la valeur réellement appliquée.

`NA_DEST_EMAIL` accepte plusieurs adresses séparées par des virgules. Elles
reçoivent alors **tout**, notices comme récapitulatifs : la constante n'a pas
d'endroit où dire qui reçoit quoi. Pour distinguer, il faut passer par la page
de réglages et laisser la constante indéfinie.

## Contenu du dossier

| Fichier | Rôle |
|---|---|
| `notice-archeomed.php` | Plugin principal : formulaire, contrôles, envoi |
| `class-notice-archeomed-docx.php` | Générateur du DOCX stylé |
| `modele-metopes.docx` | Gabarit Métopes au format DOCX (styles + mise en page) |
| `class-notice-archeomed-settings.php` | Page de réglages (Réglages > Notice Archéomed) |
| `class-notice-archeomed-file.php` | La file d'attente : inscription, expédition différée, reprises |
| `class-notice-archeomed-rtf.php` | Générateur du RTF stylé — le repli |
| `class-notice-archeomed-accessibilite.php` | L'onglet Accessibilité : contrôle axe-core du formulaire, trace de l'audit Ara |
| `modele-metopes.docx` | **Feuille de styles Métopes de référence** |
| `modele-metopes.rtf` | La même feuille dans l'autre format, pour le repli |
| `class-notice-archeomed-relecture.php` | La relecture sur le site : rôles, verrou, états, journal, versions, Word à suivi de modifications |
| `.htaccess`, `index.php` | Empêchent le téléchargement direct et le listage du dossier |

## Le surlignement des styles

La feuille Métopes colore le fond de vingt-neuf styles ; deux d'entre eux sont
employés ici, ceux du responsable et du co-auteur. **Ce surlignement se
garde.** Il vient du gabarit et non du plugin, et c'est un repère que Métopes
a voulu : le retirer ferait diverger la revue de la norme, pour un agrément
d'affichage — et il faudrait le refaire après chaque export du gabarit.

## Une règle qui vaut pour la suite

**Le document sait ce qu'il est ; on ne le lui dit pas.** Toutes les méthodes
qui écrivent passent par `plain()`, `char_run()` et `hyperlink()`, que le
document rend dans son propre langage — DOCX ou RTF. Une seule recevait le
format en paramètre, et c'est par elle que le défaut est arrivé : la variable
perdue lors d'un remaniement valait « nul », donc jamais « docx », et la ligne
de commune sortait en RTF au milieu d'un DOCX, c'est-à-dire vide.

Il n'y a plus de format à passer nulle part. Si une méthode d'écriture est
ajoutée un jour, qu'elle demande au document plutôt qu'à son appelant.

## Principe de la génération

Le générateur ne réécrit pas la feuille de styles : il recopie l'en-tête du
modèle, puis ajoute un corps de document paragraphe par paragraphe. Les
styles sont désignés **par leur nom**, le numéro (`\s231`…) étant résolu à
la volée en lisant la table des styles du modèle.

Conséquence pratique : quand Métopes fait évoluer sa feuille de styles, vous
déposez le nouveau gabarit depuis **Réglages ▸ Feuille de style Métopes**, et
rien d'autre — les styles sont reconnus par leur nom.

Le `.docx` est celui qui compte. Si l'hébergement n'a pas ZipArchive, pensez
à fournir aussi le `.rtf` : ouvrez un document appliquant le nouveau gabarit
et enregistrez-le au format RTF.

Un style qui aurait disparu du modèle ne provoque pas d'erreur : le
paragraphe concerné sort en Normal et se repère à la relecture.

## Les métadonnées dans la notice imprimée

Depuis 2026, les métadonnées ne restent plus cantonnées au bloc d'indexation :
elles paraissent en tête de notice, dans l'ordre de la mise en page et
**sans les identifiants ARK**, qui ne se lisent pas dans un volume.

```
[TEI_Titre 2+notice]                          Aix-en-Provence (Bouches-du-Rhône). Rue des Chartreux
[TEI_archeoCHR_fieldwork_method]              Nature de l'opération : étude de bâti
[TEI_archeoCHR_keywords_subjects:chronology]  Période historique : Temps modernes, Époque contemporaine
[TEI_archeoCHR_fieldwork_year]                Année de l'opération : 2024
[TEI_archeoCHR_IDpatriarche]                  Numéro d'autorisation : 2269
[TEI_archeoCHR_reportlink]                    Rapport final : https://…
[TEI_linked_data]                             Jeu de données : https://doi.org/…
[TEI_archeoCHR_holder]                        Organisme porteur de l'opération : …
[TEI_archeoCHR_keywords_subjects]             Mots-clés : couvent, chartreux, cloître
```

Un **point** sépare la commune du lieu-dit, qui s'écrit en italique. Les
natures gardent la casse de Pactols — « étude de bâti » —, les périodes
prennent une capitale initiale — « Bas Moyen Âge ». Le bloc de responsabilité
reste collé au point final du texte et porte lui-même le point final de la
notice, dans la parenthèse : « (Responsable de l'opération : Georges Perec,
CNRS ; co-responsable de l'opération : Claude Burgelin, Université
Sorbonne.) ». Une institution qui finit déjà sur un point (« etc. »), un
« ? », un « ! » ou des points de suspension n'en prend pas un second ; le
bloc mis à part dans son propre paragraphe finit de même.

Le **jeu de données** de l'opération (Nakala, Zenodo, un DOI…) est facultatif :
quand l'auteur le donne, il suit le rapport final, son adresse en lien. La
direction décidera s'il reste à l'impression ; son style se règle comme les
autres (« à supprimer » pour le tenir hors du volume).

## Le nom des illustrations

Pas d'accent, pas d'espace, le souligné pour seul séparateur. C'est la règle
de la chaîne éditoriale de la revue, suivie à la lettre : un dossier passé
d'un outil à l'autre doit garder ses noms.

« Briollay_49_ Palaisjustice.TIF » devient « Briollay_49_Palaisjustice.tif ».
La casse est gardée, l'extension passe en bas de casse.

Le nom se bâtit sur un modèle réglable, dans **Réglages ▸ Iconographie** :

| Jeton | Ce qu'il vaut |
|---|---|
| `{numero}` | le numéro en préparation — « AM55 » |
| `{rubrique}` | le rang de la rubrique — « 2 » pour la deuxième |
| `{commune}` | la commune de la notice |
| `{lieu_dit}` | le lieu-dit — il distingue deux notices d'une même commune |
| `{annee}` | l'année de l'opération |
| `{n}` | le rang de la figure dans la notice |

Le modèle par défaut est
`{numero}_{rubrique}_{commune}_{lieu_dit}_{annee}_Fig_{n}`.

**`{n}` est obligatoire** : sans lui, deux illustrations d'une même notice
porteraient le même nom et l'une écraserait l'autre sans bruit.

**`{lieu_dit}` l'est presque.** Deux notices d'une même commune et d'une même
année, cela se voit dans chaque fascicule — l'AM55 en compte trois paires
pour la seule rubrique II. Sans le lieu-dit, leurs figures portent le même
nom. L'assemblage les distingue alors par un suffixe (`…_Fig_1_2.jpg`) et
l'écrit dans le `lisez-moi`, mais le lieu-dit se lit mieux : les réglages
avertissent quand il manque.

## Ce que la résolution fait, et ne fait pas

La résolution **s'inscrit, elle ne se fabrique pas**. Porter une image à
1200 dpi en inventant des pixels l'abîmerait sans rien apporter : le plugin
enregistre la densité voulue dans le fichier, et c'est elle que la mise en
page lit pour savoir à quelle taille poser la figure. Le nombre de pixels,
lui, ne vient que du fichier d'origine.

Une illustration multipage — un PDF de deux pages, un TIFF multi-images — ne
donne que sa **première vue**. Les charger toutes puis les aplatir les
superposerait en une bouillie.

## Les deux définitions

`icono/br` porte la basse définition, celle que le document Word appelle en
lien : elle sert à voir la figure à sa place, non à l'imprimer. `icono/hr`
porte la haute définition, qui part à la mise en page. Largeur, résolution et
qualité se règlent pour chacune ; les valeurs sont bornées, un « 0 » en
largeur donnerait une image vide et un « 20000 » épuiserait le serveur.

Par défaut, la basse définition fait **2 500 px au plus sur son grand côté**
(hauteur ou largeur), à 150 dpi, et **un méga-octet environ** (1 000 Ko, à 10 %
près) ; la qualité baisse par paliers jusqu'à ce que le fichier tienne. La
haute définition d'une photographie tient dans la norme de la revue,
**10 × 15 cm à 300 dpi** (1 181 × 1 772 px), sans jamais agrandir.

### Les TIFF et les PDF, quand le serveur n'a pas Imagick

Sans Imagick, le serveur ne sait pas lire un TIFF ni un PDF. C'est alors le
**navigateur de l'auteur** qui le lit — UTIF.js pour un TIFF (`assets/utif/`,
chargé seulement dans ce cas), pdf.js pour un PDF (`assets/pdfjs/`, 1,4 Mo,
chargé seulement quand un auteur choisit un PDF) — et qui en tire des JPEG,
joints à l'envoi dans des champs cachés par rang de figure (`na_conv_hr_N`,
`na_conv_br_N`) :

- la **haute définition**, dans la boîte 10 × 15 cm à 300 dpi, tournée comme
  l'image ; la **basse**, aux mesures réglées de la basse définition ;
- la densité est inscrite dans l'en-tête JFIF du JPEG ;
- le TIFF original part aussi, et reste dans `icono/originaux` ;
- **un PDF** donne les deux mêmes JPEG, dessinés par pdf.js à partir de sa
  **première page** (l'auteur est prévenu s'il en compte plusieurs, et pdf.js
  tourne sans « eval », un PDF n'ayant pas à faire exécuter de code à la
  bibliothèque) ; la haute définition tient dans la même boîte que celle d'un
  TIFF, la page étant agrandie ou réduite pour y entrer. Le **PDF d'origine reste
  dans `icono/originaux`** ;
- le serveur reconnaît chaque JPEG à son contenu et à ses mesures
  (`jpeg_converti_acceptable()`), le range à demeure, et en tire le dossier
  Métopes (`icono/hr`, `icono/br`), le Word, la relecture et l'aperçu du courriel.

Ce qui ne passe pas est **refusé, et l'auteur enregistre lui-même la figure en
JPEG** : un TIFF ou un PDF que le navigateur ne sait pas lire (compression
rare, fichier abîmé, PDF protégé, lecture qui dépasse une minute), de plus de 36
millions de pixels, ou qui ferait dépasser la limite de taille une fois converti.
Un navigateur sans canvas ou sans `DataTransfer` refuse aussi la conversion.
Les couleurs suivent le profil de l'image : voir « Les couleurs » ci-dessous.

Les TIFF en 8 et en 16 bits par couche (gris, RVB, CMYK, avec ou sans couche de
transparence, posée sur du blanc) se lisent dans le formulaire même
(`rgbaDuTiff`) : UTIF ne lit le CMYK qu'en 8 bits, et un CMYK en 16 bits,
celui que Photoshop enregistre pour l'impression, sortait rayé et dédoublé.
UTIF garde les images en 1, 2 ou 4 bits et à palette. Un TIFF aux couches
rangées l'une après l'autre (`PlanarConfiguration` 2) est refusé.

### Un TIFF trop lourd, passé en JPEG

Depuis la version 3.73, un TIFF refusé pour son poids — plus que la limite par fichier, ou ce
qui ferait dépasser la limite de l'ensemble — n'est plus un cul-de-sac. Le
refus se dit **là où l'on ajoute les figures**, sous la dernière carte ou sous
la zone de dépôt, et la page y descend ; il se lisait auparavant au-dessus des
figures, où on le découvrait des minutes plus tard. Sous le refus, un bouton
« Passer … en JPEG de haute qualité » : le navigateur lit le TIFF et l'enregistre
en JPEG (qualité 0,92) à sa **pleine définition**, avec la densité qu'il
déclarait. Le JPEG prend la place du TIFF, comme si l'auteur l'avait choisi ;
l'original TIFF ne part pas. Un JPEG encore trop lourd voit sa définition
descendre par paliers, jamais sous la norme des photographies (10 × 15 cm à
300 ppp). Ce qui ne tient toujours pas, ou dépasse 36 millions de pixels, est
refusé avec sa raison.

Cela marche avec ou sans Imagick : sans lui, le lecteur de TIFF est déjà
chargé ; avec lui, il ne se charge qu'au premier TIFF à alléger.

### Les couleurs

Une figure préparée pour l'impression porte souvent un profil
colorimétrique : un TIFF en CMJN enregistré par Photoshop décrit ses encres
par le profil du papier (FOGRA39, par exemple). Depuis la version 3.82, ce
profil est lu, et la vignette, la basse et la haute définition montrent les
couleurs de l'original, non une approximation.

- **Dans le navigateur**, un TIFF qui porte un profil passe au sRGB par
  Little CMS (`assets/lcms/`, 360 Ko, chargé seulement pour un tel TIFF), en
  intention colorimétrique relative avec compensation du point noir, comme
  Photoshop le fait par défaut : le cyan pur d'un FOGRA39 sort à 0, 159, 227,
  le noir à 29, 29, 27. Sur une figure réelle, l'écart moyen avec la
  conversion de macOS (ColorSync) est d'un point sur 255 ; l'ancienne formule
  laissait le rouge douze points trop bas. Cela vaut aussi pour « Passer en
  JPEG ».
- **Sur le serveur**, Imagick fait de même avec le profil sRGB du plugin
  (`assets/srgb.icc`), en intention perceptive. Il lui faut un ImageMagick
  construit avec Little CMS : l'onglet Hébergement le dit, à la ligne
  « Imagick : profils de couleur ».
- Un RVB qui porte déjà le profil sRGB, ou n'en porte aucun, n'est pas
  touché. Un CMJN sans profil, ou dont le profil ne se lit pas, passe encore
  par la formule de pdf.js (encres SWOP), et ses couleurs restent approchées.

L'original, quand on le garde, part tel quel, profil compris.

`icono/originaux` garde, si on le demande, les fichiers tels que l'auteur les
a envoyés : rien de ce qu'il a fourni ne disparaît alors dans une conversion.

## La page de relecture

Le paquet porte un `relecture.html` : la rubrique entière lue dans un
navigateur, **figures comprises**. On la double-clique, elle s'ouvre, rien à
installer.

Elle existe parce qu'un document aux images liées montre des cadres vides
tant qu'il n'est pas ouvert depuis le dossier du paquet — et que Word résout
mal les chemins relatifs. Fabriquer un PDF côté serveur demanderait
LibreOffice, qu'un hébergement mutualisé n'a pas. Qui veut un PDF imprime la
page depuis son navigateur : la feuille de style empêche qu'une figure se
coupe entre deux pages.

Elle se bâtit dans la classe du plugin et non dans celle du paquet, pour
tenir ses métadonnées des mêmes fonctions que le document : deux mises en
forme parallèles finiraient par ne plus dire la même chose. Une figure sans
basse définition y paraît en rouge, plutôt qu'en blanc.

## Le bloc « à supprimer »

Tout ce qui sert à la rédaction et non au lecteur : l'avis de correction, les
avis du dépôt (« À vérifier »), le message de l'auteur, la mention d'une
autorisation de reproduction jointe, le lien vers les originaux, une figure
déposée sans titre ni légende, les coordonnées des responsables et
l'indexation Pactols avec ses ARK : la rédaction en a besoin, le lecteur non. Elles portent donc un
style de paragraphe nommé **« à supprimer »**, gris à l'écran.

Dans Word : clic droit sur ce style dans le volet des styles, puis
« Sélectionner toutes les occurrences ». Tout le bloc se prend d'un coup et
s'ôte d'une touche — c'est ainsi qu'on obtient la version propre à passer au
XML.

**Ce style n'est pas dans le gabarit Métopes, et n'y sera pas.** Le gabarit
s'exporte à neuf à chaque évolution de la feuille, et ce qu'on y ajouterait à
la main serait à refaire. Il est injecté dans la feuille de styles au moment
d'écrire le fichier : le gabarit livré reste intact, le document produit porte
le style. C'est pourquoi `./verifier-les-styles` ne le réclame pas.

En RTF — le repli des hébergements sans `ZipArchive` — le style est inconnu et
les paragraphes sortent en Normal, sans que rien ne casse.

## Correspondance champ / style

La table ci-dessous est la **correspondance d'origine**. Elle se change bloc
par bloc dans **Chronique ▸ Réglages ▸ Styles Métopes** : chaque menu ne
propose que les styles du bon type — paragraphe ou caractère — que porte la
feuille installée, la correspondance d'origine y est marquée, et un style
enregistré qui aurait disparu de la feuille est signalé en rouge. C'est là
qu'on tranche les questions ouvertes avec Métopes : un numéro d'autorisation
qui ne serait plus l'identifiant Patriarche (« à supprimer », ou un autre
style), un bloc de responsabilité dans son propre paragraphe
(`TEI_archeoCHR_authority`, sans parenthèses) plutôt que collé au texte. Les
blocs d'index Pactols nomment le style réglé dans leur `rend`.
« Rétablir les correspondances d'origine » remet tout d'un geste.

Le **texte alternatif** d'une figure part toujours dans le texte de
remplacement de l'image (`descr` de `wp:docPr`) : c'est là que Métopes le lit
et le convertit en `figDesc`. Son paragraphe est facultatif et ne paraît pas
d'origine (« — aucun paragraphe — »), et il vaut mieux l'y laisser : un
document stylé pour Métopes perd à la conversion le paragraphe
`TEI_figure_alttext`, et `TEI_figure-alttext` n'est pas reconnu. Si la
rédaction lui choisit tout de même un style, il se pose **à la fin du bloc de
figure**, après le titre, la légende et les crédits, juste avant
`TEI_figure_end`. La **description détaillée** se publie en fin de légende :
un ou plusieurs paragraphes `TEI_figure_caption` qui suivent la légende et
précèdent les crédits, le premier ouvert par « Description : ». C'est la
seule place qui passe aujourd'hui à la publication ; le bloc « Description
détaillée » de l'onglet Styles en change le style (« à supprimer », par
exemple, pour la tenir hors de l'imprimé).

| Bloc de la notice | Style Métopes |
|---|---|
| Avis de correction (dépôt corrigé) | à supprimer |
| Rubrique principale (tête de notice) | `Title` |
| Sous-rubrique (famille d'opérations) | `TEI_Titre 1+rubrique` |
| Renvoi vers une autre rubrique (fascicule) | `TEI_Titre 2+notice` |
| Commune (département). Lieu-dit | `TEI_Titre 2+notice` |
| Nature de l'opération | `TEI_archeoCHR_fieldwork_method` |
| Autres lieux | `TEI_keywords_subjects:geography` |
| Période historique | `TEI_archeoCHR_keywords_subjects:chronology` |
| Année de l'opération | `TEI_archeoCHR_fieldwork_year` |
| Numéro d'autorisation, identifiant Patriarche | `TEI_archeoCHR_IDpatriarche` |
| Rapport final (lien) | `TEI_archeoCHR_reportlink` |
| Jeu de données (lien, facultatif) | `TEI_linked_data` |
| Organisme(s) porteur(s) de l'opération | `TEI_archeoCHR_holder` |
| Mots-clés | `TEI_archeoCHR_keywords_subjects` |
| Texte de la notice | Normal |
| Intertitre du texte (T1) | `heading 3` (Titre 3) |
| Élément de liste du texte (bouton Liste à puces) | `List Paragraph` (Paragraphe de liste), avec la puce de Word |
| Image d'une illustration (texte alternatif dans son texte de remplacement, `descr`) | Normal |
| Texte alternatif, à la fin du bloc de figure | aucun paragraphe (réglable) |
| Description détaillée, en fin de légende (« Description : … ») | `TEI_figure_caption` |
| Sous-rubrique de la rubrique V : « V. A1. – Céramique, terres cuites architecturales, verrerie : opération de terrain » | `TEI_Titre 1+rubrique` |
| Sous-rubrique à choisir (notice V d'avant les sous-rubriques A, B, C) | à supprimer |
| Titre d'une illustration, numéro en `TEI_figure_num_inline` | `TEI_figure_title` |
| Légende d'une illustration | `TEI_figure_caption` |
| Crédits d'une illustration | `TEI_figure_credits` |
| Avis, commentaires, autorisations, contacts, indexation | à supprimer |

Les renvois prennent le style du titre de notice : ce sont des entrées sans
corps. Métopes n'a pas de style pour un contenu qu'on voudrait taire à
l'écran, et ses styles locaux redeviennent un paragraphe ordinaire à l'export,
qui se rangerait alors dans la notice précédente.

Styles de caractère appliqués dans le bloc de responsabilité et dans la
liste des contacts :

| Élément | Style de caractère |
|---|---|
| Responsable et co-responsable | `TEI_archeoCHR_name:fld` |
| Co-auteur | `TEI_archeoCHR_name:aut` |
| Institutions de rattachement | `TEI_archeoCHR_aff_inline` |
| Adresses et ARK | `Hyperlink` |

Dans un renvoi vers une autre rubrique, le lieu-dit et la rubrique citée
sont en italique, et la rubrique est mentionnée sans son numéro romain.

Aucune police ni corps n'est imposé par le générateur : chaque paragraphe
reçoit uniquement son style, et hérite donc des polices et tailles définies
dans la feuille de styles Métopes du modèle. Modifier le modèle suffit à
changer l'apparence de toutes les notices produites ensuite.

Le lieu-dit passe en italique dans le titre de notice. Ce que l'auteur y a
mis en italique (le nom d'un navire, d'une œuvre) repasse en romain ; ses
petites capitales et ses exposants restent. Le bloc
« Responsable de l'opération : … » se colle à la fin du dernier paragraphe du
texte, entre parenthèses, comme dans le fichier de stylage de référence.

Les ARK Pactols sont posés en liens cliquables, dont le libellé est le terme
lui-même ; l'adresse de l'ARK complet est la cible du lien.

## Nom du fichier joint

`notice-archeomed-<Commune>-<Année>-<aléa>.docx` (`.rtf` sur un hébergement
sans `ZipArchive`), par exemple `notice-archeomed-Caen-2025-87f1ea09.docx`.
La commune et l'année en clair facilitent le classement par rubrique avant
montage du volume. Ses propriétés portent le titre de la notice ; la page est
en A4 ; chaque image a pour texte de remplacement le texte alternatif de l'auteur (à
défaut, pour une notice d'avant ce champ, le titre de sa figure).

## Texte saisi dans l'éditeur

Depuis la 3.106, **la barre de mise en forme reste en haut de l'écran**
tant que le texte y est : dans une longue notice, on ne remonte plus pour
mettre un mot en italique. Elle revient à sa place quand le texte quitte
l'écran. Le collage CSS y suffit d'ordinaire ; sous un thème qui enveloppe la
page dans un bloc à défilement, ce qui le défait, la barre se fixe d'elle-même
à la largeur de l'éditeur, sous la barre d'administration s'il y en a une.

Gras, italique, exposant et indice sont convertis. L'**intertitre** se pose
par le bouton T1 de la barre (écrit `h3`). La notice n'en a qu'un niveau :
sous le titre de la notice, qui est de niveau 2, c'est un titre de niveau 3,
que le document pose sous le style du bloc « Intertitre du texte » de
l'onglet Styles Métopes — « heading 3 » (Titre 3 de Word) par défaut. Un titre
collé dans l'éditeur, de quelque niveau qu'il soit, et le `h4` d'une notice
ancienne deviennent un intertitre T1 ; un intertitre ne reçoit pas
l'avis de ponctuation finale — il en reçoit un, au contraire, s'il finit par
« . », « , », « ; » ou « : » (« ? », « ! » et « … » restent permis) —, et le bloc de responsabilité ne s'y colle pas.
Depuis la 3.102, la **liste à puces** se pose par le bouton Liste à puces de
la barre (écrite `<ul><li>`). Elle n'a qu'un niveau et qu'une sorte : une
liste collée, numérotée ou emboîtée, devient une liste à puces à plat, et un
tiret tapé en début de ligne reste un tiret. Au Word, chaque élément prend le
style du bloc « Élément de liste du texte » — « List Paragraph » (Paragraphe
de liste) par défaut — et la puce de Word, la vraie, celle du bouton
« Puces » : c'est elle que la conversion Métopes reconnaît pour une liste,
une puce tapée n'y étant qu'un caractère. La définition de cette puce
s'ajoute à la numérotation du modèle, après les siennes ; un document sans
liste garde la numérotation du modèle telle quelle. Un élément de liste ne
reçoit pas l'avis de ponctuation finale, et le bloc de responsabilité ne s'y
colle pas. Une citation collée depuis Word devient un paragraphe, au lieu de
se souder au suivant. Les entités HTML sont
résolues. Les caractères accentués et typographiques (œ, €, guillemets,
apostrophes courbes) sont encodés en notation Unicode RTF, que Word et
LibreOffice lisent sans réglage particulier. Un balisage mal équilibré est
refermé automatiquement et ne corrompt pas le fichier.

## Vérification après installation

Soumettre une notice de test comportant : un accent et une ligature (œ), du
gras et de l'italique, deux paragraphes, un mot-clé Pactols et un
co-responsable. Ouvrir le RTF reçu dans Word, volet des styles affiché, et
contrôler que chaque paragraphe porte le style attendu du tableau ci-dessus.

## Le titre d'une notice, et ses lieux

Une opération porte parfois sur plusieurs communes, et parfois sur un
territoire qui n'en est pas une :

> Beneuvre, Bure-les-Temple, Duesme, Vanvey (Côte-d'Or), Mont Aigu
>
> Grand Est (Champagne, Alsace, Lorraine), Paysage et architecture…

Les trois champs avaient le bon nombre de cases mais les mauvais noms. Ils
s'appellent désormais ce qu'ils sont :

| Champ | Ce qu'il reçoit |
|---|---|
| **Lieu(x) de l'opération** | jusqu'à cinq, chacun choisi dans Pactols avec son ARK — une commune, une région, un territoire |
| **Précision entre parenthèses** | le département le plus souvent, mais aussi « Champagne, Alsace, Lorraine » |
| **Lieu-dit, adresse ou complément de titre** | ce qui suit la parenthèse, en italique : un lieu-dit ou le titre d'un projet collectif |

Le titre se compose des lieux séparés par des virgules, puis de la parenthèse,
puis du complément en italique.

**Le lieu-dit a sa barre de mise en forme**, depuis la version 3.97 : petites
capitales (Pc), italique et exposant, pour « XII<sup>e</sup> s. » ou le nom d'un
navire. L'italique posé par l'auteur s'imprime en romain dans le titre, qui
est lui-même en italique — l'aperçu du titre le montre ainsi. Le champ reste
d'une ligne : Entrée ne crée pas de ligne, un collage sur plusieurs lignes se
remet sur une. La même barre se trouve à la relecture. Le texte du lieu-dit
reste ce qu'il était (contrôles, nom des fichiers, objet du courriel) ; la
mise en forme voyage à côté et se perd, sans perdre une lettre, si le texte
est retouché ailleurs. La parenthèse se remplit toute seule à partir
du **premier** lieu quand c'est une commune ; les suivants sont réputés du même
département.

**La parenthèse n'est pas indexée.** Elle est du texte : « Champagne, Alsace,
Lorraine » n'y reçoit aucun identifiant. Quand elle nomme des territoires, il
faut les reprendre dans « Lieux autres que celui de la notice » pour qu'ils en
aient un — le formulaire le dit à l'endroit où la question se pose.

Chaque lieu garde son identifiant Pactols et paraît dans l'indexation :
c'est la partie chère de la saisie, celle qu'un humain est allé chercher dans
un thésaurus.

Les notices reçues avant cette version portent une commune unique ; le
fascicule les refabrique sans rien perdre.

## La nature de l'opération, et le code Patriarche

Depuis la version 3.107, la section « Nature de l'opération » s'ouvre, sous la
consigne « Cochez toutes celles qui s'appliquent », sur deux cases de
**cadre** : **Préventive** et **Programmée**. Vient ensuite le groupe **Type
d'opération**, dans l'ordre voulu par la rédaction : Fouille, Étude de bâti,
Sondage, Diagnostic, Surveillance de travaux. Les prospections, les projets
collectifs et les autres natures suivent, comme avant.

Le cadre et le type se recomposent à l'enregistrement : Fouille et Préventive
donnent « Fouille préventive », Étude de bâti et Programmée « Étude de bâti
programmée », chacun avec son identifiant Pactols. La notice garde ainsi les
mêmes termes qu'auparavant, et le document Word, le fascicule et l'ordre du
volume n'en voient rien. Un type sans cadre reste tel quel. Les cadres ne
s'appliquent qu'aux types : une prospection reste une prospection, et un cadre
coché à côté d'elle, sans type, ajoute « Opération d'archéologie préventive »
ou « programmée ». Un cadre seul ne suffit pas : il faut cocher une nature.

Une notice reçue avant la 3.107 se rouvre — à la correction, à la relecture —
dans ces cases : « Fouille préventive » coche Fouille et Préventive,
« Archéologie du bâti » coche Étude de bâti, « Opération de diagnostic »
Diagnostic. Si l'on ne touche pas aux cases, la notice n'est pas réécrite :
une notice qui mêlait une fouille préventive et un sondage programmé ne
devient pas, sans qu'on l'ait voulu, une fouille et un sondage préventifs. Un
brouillon gardé par le navigateur avant la mise à jour se rouvre de même, et
une page ouverte avant la mise à jour envoie encore les anciens termes, qui
sont acceptés tels quels.

Le **code d'opération Patriarche** (le numéro d'OA) est **obligatoire** pour
toutes les notices : c'est lui qui permet de retrouver le rapport auprès du
service régional de l'archéologie. Le navigateur le demande avant l'envoi, et
le serveur le redemande. Le numéro d'arrêté reste facultatif.

## La hiérarchie du document

Un fascicule, et une notice isolée, se composent ainsi :

```
[Title]                            IV. – Sépultures et nécropoles
[TEI_Titre 1+rubrique]             IV. 1. – Opérations de terrain
[TEI_Titre 2+notice]               Beneuvre, Vanvey (Côte-d'Or), Mont Aigu
[TEI_archeoCHR_fieldwork_method]   Fouille programmée
[TEI_archeoCHR_fieldwork_year]     Année de l'opération : 2024
…
```

Le tiret long après le numéro est celui de la revue, pour la rubrique comme
pour la sous-rubrique. Il n'apparaît qu'à l'impression : la liste du
formulaire garde sa forme simple, « IV. Sépultures et nécropoles ».

La nature de l'opération est passée sous le titre de la notice, avec ses
identifiants Pactols : c'est un renseignement sur l'opération, non une
division du volume. La place de titre de niveau 1 revient à la sous-rubrique,
numérotée sous sa rubrique.

Dans un fascicule, la rubrique se pose une fois en tête et la sous-rubrique
une fois par groupe ; les notices s'y rangent par commune, dans l'ordre
alphabétique.

## Les illustrations

Chaque fichier déposé reçoit sa propre carte dans le formulaire, nettement
détachée de la page (« Figure 1 » en titre, fond teinté). Elle a deux parties. Sur les trois figures au plus, l'aide demande de
privilégier **un plan, une vue d'ensemble et un échantillon du mobilier**.
**Pour la publication** : le **titre** et les **crédits**, obligatoires, la
**légende** et l'**autorisation de reproduction**, facultatives. Puis, en fin de
carte, encadré et teinté parce que c'est un autre travail : **Pour un public
empêché de voir**, le texte alternatif, obligatoire. Les champs obligatoires
portent un astérisque, dans tout le formulaire. Un champ libre commun recevait
tout auparavant, et la rédaction devait deviner quelle ligne allait avec quelle
image.

La carte montre une vignette de l'image : d'un JPEG, d'un PNG, d'un TIFF
ou d'un PDF. Le TIFF et le PDF ne s'affichent pas tels quels : le navigateur
en dessine une vignette pour l'écran seulement (UTIF.js ou pdf.js, chargés à
ce moment, première page du PDF). Avec Imagick, c'est le serveur qui
convertit, et rien de cette vignette ne part ; sans lui, c'est la conversion
du navigateur qui la donne. Un clic l'agrandit dans une fenêtre par-dessus la page, à
la taille de l'écran : la légende et le texte alternatif s'écrivent mieux
devant l'image entière. Échap, le bouton « Fermer » ou un clic à côté la
referment.

**Le titre, la légende et la description détaillée ont la barre du
lieu-dit**, depuis la version 3.99 : petites capitales (Pc), italique et
exposant, pour un nom latin, un siècle ou un appel de cote. Le titre reste
d'une ligne (Entrée n'y crée rien) ; la légende et le champ « Pour un public
empêché de voir » gardent leurs paragraphes. Dans ce dernier, **le premier
paragraphe, le texte alternatif, reste sans mise en forme** : le lecteur
d'écran n'en ferait rien, et Métopes le lit en texte brut. Les boutons y
sont sans effet, une marque collée s'y efface aussitôt, et une ligne sous
le champ le dit ; la barre ne sert qu'à la description qui suit. La mise en
forme passe dans le Word, le courriel, la page de relecture du dossier et
le formulaire de correction ; la même barre se trouve à la relecture. Comme au
lieu-dit, le texte reste ce qu'il était et la forme voyage à côté : si le
texte est retouché ailleurs, elle se perd sans perdre une lettre, et le
texte s'imprime en romain. Sans l'éditeur (script bloqué), le champ ordinaire
reste à sa place.

### La taille d'impression

Chaque carte dit à quelle taille la figure peut s'imprimer : « Imprimable
jusqu’à 25 × 17 cm environ, à 300 ppp ». L'auteur juge mieux d'une mesure en
centimètres que d'un nombre de pixels. Un plan, une carte, un graphique ou un
tableau se comptent à la densité du trait, réglée dans les normes.

### Une figure envoyée à part

Un fichier trop lourd pour le formulaire ne se perd pas : l'auteur le dépose
sur un service de transfert (FileSender de RENATER, espace de son
établissement) et ajoute une **figure envoyée à part**. C'est une carte sans
fichier, qui prend place parmi les trois et se range comme les autres. On y
colle le lien de téléchargement, sa date d'expiration et le nom du fichier
dans l'envoi, puis on remplit les mêmes textes que pour une figure déposée.
La carte suivante reprend d'office le dernier lien donné. Un fichier choisi
trop lourd ouvre lui-même une telle carte.

Le Word de la notice porte, à la place de l'aperçu, un cadre rouge « à
remplacer par l'original envoyé à part ». Le courriel et la fiche donnent le
lien. Il n'y a pas de version allégée de ces figures : la rédaction télécharge
l'original et le dépose dans le dossier de la notice sur sDrive, sous le nom
de l'envoi ou sous « Fig_N ». Le fichier `a-deposer.txt` de ce dossier le
rappelle, figure par figure.

### Le texte alternatif

Il est **obligatoire** pour toute figure déposée, dans le champ « Pour un public
empêché de voir » : quelques mots qui disent ce que montre l'image, pour les
personnes malvoyantes ou non voyantes, dans la
démarche d'accessibilité de la revue. L'aide le dit en une ligne : il ne
répète pas la légende, il restitue l'information visuelle utile, sans
« Image de », que le lecteur d'écran annonce déjà. Deux exemples de la
rédaction se déplient sous l'aide (« Exemples ») :

| Légende | Texte alternatif |
|---|---|
| Fig. 12. Plan général des vestiges de la phase 2. | Deux bâtiments occupent l'ouest de l'enclos fossoyé, entourés de fosses et de silos. |
| Fig. 8. Vue du foyer du bâtiment 1. | Foyer quadrangulaire installé contre la paroi nord du bâtiment. |

**La nature de la figure.** Un choix **obligatoire** — photographie, plan
(relevé, dessin), carte, graphique ou diagramme, planche, tableau en image,
autre — posé en tête du bloc « Nature de la figure et texte alternatif pour un
public empêché de voir »
(`illus_type[]`, gardé avec la figure : brouillon, reprise, correction).
Facultatif, il serait resté vide ; « Autre » sert à ce qui n'entre nulle part.
L'oubli se signale comme celui du texte alternatif (sous le champ, dans le
récapitulatif, au serveur : `illus_type_N`). Une notice d'avant le champ, reprise
en correction, demande de le choisir. Elle sert à trois choses :

- un **conseil** propre à chaque nature s'affiche sous le choix
  (`Notice_Archeomed_Controles::aides_par_type()`) ;
- un **graphique** ou un **tableau en image** sans description détaillée reçoit
  l'avis « figure de type … : son contenu ne se lit pas à l'écoute. Recopiez
  les données… ». Pour un plan, une carte, une planche ou une photographie, le
  texte alternatif suffit : aucun avis ne réclame une description de plus à
  qui a rempli le champ (elle reste possible, à la ligne) ;
- elle figure dans le courriel de la rédaction, la page de relecture et la fiche
  (« Nature : Carte »), non dans le document Word : la chaîne Métopes ne la
  lit pas.

L'aide du **titre** demande une ligne informative, qui nomme ce que montre la
figure et son objet ; celle de la **légende**, d'y reprendre ce qu'un lecteur
qui ne voit pas l'image doit savoir — la clé d'un plan ou d'une carte, les
inscriptions, les étiquettes, l'échelle, l'orientation. L'aide des
illustrations rappelle d'appeler chaque figure dans le texte, « (fig. 1) ».

Un oubli se signale comme pour les autres champs
obligatoires : sous le champ, et dans le récapitulatif, qui nomme la figure et
son fichier. Le serveur refuse de même un envoi sans le script, garde la
saisie et dit quelle figure décrire.

- **Longueur.** Un compteur sous le champ, sur 300 caractères. Le premier
  paragraphe s'arrête là : ce qui est tapé ou collé au-delà n'entre pas
  (depuis la 3.82), et le compteur dit que le détail se met à la ligne. Le
  serveur coupe de même à 300 en le nommant dans les avis — limite dure de la
  rédaction, non réglable. Au-delà d'un seuil plus bas, le récapitulatif
  donne un avis, sans refus : 150 caractères par défaut, réglable dans
  **Normes éditoriales ▸ L'accessibilité** (de 20 à 300). Le RGAA 4.1, plus strict, « recommande fortement »
  80 caractères (glossaire, « Alternative courte et concise », test 1.3.9).
- **Avis.** Le serveur n'en refuse aucun. Le navigateur, lui, en tient quelques-uns
  pour des points à corriger avant l'envoi (voir « Les avis qui bloquent » plus
  bas) ; tous les autres restent des conseils. Le navigateur les donne à la sortie du
  champ, le serveur les range sous « À vérifier », **dans la même phrase** :
  le formulaire reçoit du serveur les phrases, les motifs, les listes et les
  seuils (`Notice_Archeomed_Controles::regles_d_accessibilite()`). Chaque
  avis suit « Fig. N : ».
  - le texte est trop long (voir plus haut) ;
  - il **reprend le titre ou la légende** : identique, ou presque, une fois
    ôtés la casse, les accents, la ponctuation, les blancs et « Fig. N ».
    « Presque » : l'un contient l'autre, le plus court ayant au moins trois
    mots, ou les deux partagent au moins 80 % de leurs mots distincts
    (coefficient de Dice). Seuils [HYPOTHÈSE], choisis pour qu'un texte de
    deux mots pris dans une longue légende — « Le fossé » — ne déclenche
    rien ;
  - il **ne dit rien de l'image** : « image », « figure », « fig. », « photo »,
    « photographie », « illustration », « dessin » ou « plan », seul ou suivi
    d'un numéro (« Photo 3 », « Fig. 2 ») ;
  - il **n'est qu'un nom de fichier** : il finit par une extension d'image
    (`image1.jpg`, `DSC_0042.JPG`), prend le nom que donne un appareil
    (`IMG_20240512_101010`, `P1030456`), ou reprend le nom du fichier déposé,
    avec ou sans extension. Ces deux avis-là sont seuls : dire en plus qu'un
    « Photo » reprend le titre « Photo » n'apprendrait rien ;
  - il commence par « Image de », « Photo de », « Photographie de » ou
    « Illustration de » ;
  - lui ou la légende renvoie à une couleur seule (« en rouge », « zones
    vertes ») — liste des couleurs [HYPOTHÈSE] ;
  - **données à recopier** : la figure est un graphique ou un tableau en image
    (nature choisie) et la description détaillée est vide ;

  La case « l'accessibilité des figures » des avis du dépôt (Normes
  éditoriales) les tait tous. Un site dont les avis étaient déjà enregistrés
  la trouve cochée : une case ajoutée après coup n'est jamais tenue pour
  décochée.
- **Les avis qui bloquent.** Depuis la 3.64, le navigateur refuse l'envoi tant
  que ces points ne sont pas réglés, et les liste dans le récapitulatif :
  les appels de figure (la figure 2 appelée avant la 1, une figure jamais
  appelée, une figure appelée sans être jointe, des figures jointes qu'aucun
  appel ne nomme) et un texte alternatif qui
  reprend le titre ou la légende, n'est qu'un nom de fichier, ou ne dit rien
  (« Photo 3 »). **Ne bloquent pas** : la définition de l'image, les données
  d'un graphique, la longueur et la couleur du texte alternatif, tous les avis
  du texte (ponctuation, ordinaux, sigles…). Le récapitulatif les range à part,
  sous « À vérifier avant l'envoi », après « À corriger » où vont les vraies
  erreurs. Une case, « J'ai vérifié ces points : ma notice est correcte
  ainsi. L'envoyer telle quelle. », laisse partir la notice : un texte cite
  parfois les figures d'un autre article, ce que le formulaire ne peut pas savoir.
  Le serveur ne refuse rien de tout cela : seul le navigateur arrête, et une
  notice envoyée sans lui part quand même.
- **Texte simple.** Une seule ligne (retours et blancs multiples ramenés à une
  espace), en romain : ni siècles en petites capitales ni exposant, ni italique
  ni gras, au Word comme dans `descr` ; les insécables seules s'appliquent.
- **Où il va.** Le texte de remplacement de l'image du Word (`descr`), le
  courriel, la fiche d'administration, l'attribut `alt` de la page de
  relecture du dossier Métopes ; en paragraphe stylé sous l'image si la
  rédaction le règle (voir « Correspondance champ / style »).
- **Il suit sa figure** : glisser-déposer, Monter et Descendre, retrait puis
  rajout, reprise après un refus, lien de correction, brouillon de l'appareil.
- **Notices d'avant ce champ.** Elles restent valides : leur image garde son
  titre en texte de remplacement, et la page de relecture le donne en `alt`.
  La fiche les dit « À compléter ».
- **Pas de proposition automatique** par un service extérieur : c'est écarté.

### L'accessibilité des illustrations, sur la fiche

La fiche d'une notice porte l'encart **Accessibilité des illustrations** : un
tableau Figure | Légende | Crédits | Texte alternatif | Description
détaillée | État. L'appel de la figure suit les normes (« Fig. 1 »).
L'état vaut :

- **OK** ;
- **À vérifier**, avec les raisons, qui sont les avis ci-dessus. L'état se
  calcule même quand la case des avis est décochée : taire les avis aux
  auteurs ne doit pas cacher à la rédaction ce qui reste à revoir ;
- **À compléter** : pas de texte alternatif, cas des notices d'avant le champ
  — le Word porte alors le titre à sa place.

La rédaction y **corrige le texte alternatif et la description détaillée**
de chaque figure. L'enregistrement passe par `admin-post.php`, avec un jeton
et le droit `manage_options`, et applique les nettoyages et les limites du
dépôt (300 et 2 000 caractères ; un texte plus long est coupé et le message
le dit). Il ne touche dans la saisie (`_na_donnees`) qu'à ces deux textes.
Chaque valeur remplacée reste dans la métadonnée `_na_historique_figures`,
une ligne par correction : date, utilisateur, figure, champ, valeur d'avant
et d'après. L'historique s'écrit avant la saisie : aucune valeur ne
disparaît. Il se lit sous le tableau.

Un Word retéléchargé, un fascicule ou un dossier Métopes produits ensuite
portent la valeur corrigée, dans `descr` et partout ailleurs. Le courriel déjà
parti, l'encart « La notice » et les avis gardés au dépôt restent ce qu'ils
étaient.

L'encart vit dans le formulaire de la fiche, que WordPress ouvre autour de
tous les encarts. Ses champs se rattachent donc, par l'attribut `form`, à un
formulaire posé en pied de page : un formulaire ne s'imbrique pas dans un
autre.


### La description détaillée

Facultative, pour les figures complexes — plans, coupes, cartes,
graphiques : ce qu'un lecteur qui ne la voit pas doit en savoir
(organisation, repères, données). Elle n'a plus de champ à elle : l'auteur ne
comprenait pas pourquoi on lui en demandait deux. Elle s'écrit dans le même
champ que le texte alternatif, **à la ligne** : le premier paragraphe est le
texte alternatif, la suite la description détaillée
(`Notice_Archeomed_Controles::separer_l_ecoute()`, et son pendant dans le
navigateur). Un formulaire d'avant ce changement, qui l'envoie à part, est
encore lu. Texte simple, plusieurs paragraphes permis,
coupé au-delà de 2 000 caractères avec un avis. Elle suit sa figure comme le
texte alternatif, paraît dans le courriel, la fiche et la page de relecture
— sous la figure, l'image y renvoyant par `aria-describedby` —, et au Word à
la fin du bloc de figure (voir « Correspondance champ / style »).

Le Word déclare le français comme langue par défaut (`w:lang` `fr-FR`) : un
lecteur d'écran lit alors la notice et ses textes alternatifs avec la bonne
voix. Le gabarit livré le déclare déjà ; une feuille déposée qui l'omettrait
le reçoit.

Dans le document Word, chaque illustration donne un bloc de figure aux normes
Métopes — trois styles distincts que la chaîne XML sait séparer, là où tout
partait dans le seul titre de figure :

```
[TEI_figure_title]    Fig. 1 Vue générale du chantier
[TEI_figure_caption]  Le mur de terrasse vu depuis le sud, en fin de fouille.
[TEI_figure_credits]  Cliché A. Dupont, université de Caen.
```

Le bloc est encadré — `TEI_figure_start` et `TEI_figure_end` portant
`— <figure> ———…` et `— </figure> ———…` — pour que la chaîne XML sache où la
figure commence et finit, et qu'un préparateur passant d'un outil à l'autre
retrouve la même chose sous les yeux.

Les lignes vides ne produisent rien : une illustration sans crédits n'a pas de
paragraphe de crédits. Une figure déposée sans aucun texte garde pourtant son
bloc et son numéro, avec un avis « à supprimer » : on la jetait, et la
suivante prenait sa place. Un « Fig. 1 : » retapé par l'auteur en tête de son
titre s'ôte, puisque le numéro est posé par le plugin.

Chaque figure peut porter son **autorisation de reproduction** (PDF, JPEG ou
PNG, 10 Mo au plus) : elle se garde avec la notice, se télécharge depuis la
fiche sous sa figure, et part dans le courriel si elle tient dans le poids
permis. Des originaux trop lourds pour le formulaire ? L'auteur en joint des
versions allégées et donne un **lien de téléchargement** des originaux
(FileSender de RENATER, espace de partage de son établissement), avec la
**date** à laquelle le service l'efface — obligatoire dès qu'un lien est donné.
Le lien et sa date arrivent dans le courriel et le document, et la rédaction
reçoit en plus un **courriel d'alerte** à part, dont l'objet ne peut pas
passer inaperçu : « ATTENTION — LIEN À TÉLÉCHARGER DANS 12 JOURS, JUSQU’AU
24 OCTOBRE 2026 — Caen (Calvados), Le Château — réf. … ».

L'auteur reçoit **le fichier Word en pièce jointe** de son accusé de
réception : c'est le même que celui de la rédaction. Il voit ce qui a été
produit à partir de sa saisie, peut le relire et le renvoyer corrigé sans
qu'on ait à le lui redemander.

## Le récapitulatif quotidien

Chaque matin à sept heures, la rédaction reçoit un message unique listant les
notices arrivées depuis la veille, rangées dans l'ordre du volume, avec un
lien vers chacune. Sur une campagne de plusieurs milliers de sollicitations,
un courriel par notice enterre une boîte aux lettres : on ne sait plus ce qui
est arrivé, ni combien, ni si l'on a tout vu.

L'envoi individuel continue par ailleurs — le récapitulatif s'ajoute, il ne
remplace pas.

## Le ton

Le formulaire et ses messages **vouvoient**. On s'adresse à des chercheurs
qu'on ne connaît pas, sollicités par courrier : le tutoiement y détonnerait.

## Les illustrations sont conservées

Elles ne l'étaient pas : envoyées par courriel, elles étaient effacées du
serveur dans la foulée. Tout le reste d'une notice se retrouve — le texte est
en réserve, le document se refabrique — mais l'image d'un auteur, non : c'est
un TIFF de vingt méga-octets qu'on ne redemande pas six mois plus tard à
quelqu'un qui a changé d'ordinateur.

Elles vivent maintenant dans `wp-content/uploads/notice-archeomed/`, et se
retéléchargent depuis la fiche de leur notice, figure par figure, avec le
titre que l'auteur leur a donné.

**Ce dossier n'est pas censé être servi par le web.** Il porte un `.htaccess`
qui le ferme et un `index.php` vide, et les noms de fichiers sont tirés au
sort sur trente-deux caractères. Sur Apache, le `.htaccess` suffit ; sur
nginx, qui l'ignore, c'est le nom imprévisible qui protège. Le téléchargement
lui-même passe par l'administration et demande d'y être entré.

**Surveillez la place.** Quarante méga-octets d'illustrations par notice au
plus (`MAX_TOTAL_FILESIZE`, vingt avant la version 3.81, quand PHP coupait à
vingt-cinq ; le serveur de la revue en accepte soixante-quatre), sur
une campagne de plusieurs milliers de sollicitations, cela se compte en
dizaines de giga-octets. La fiche indique le poids conservé, et supprimer une
notice efface ses illustrations avec elle.

Le document Word, lui, ne se garde pas sur le serveur : il se refabrique à
partir de la saisie. L'archiver reviendrait à figer une mise en forme que la
feuille de styles fera évoluer.

### Sur sDrive, quand le site y est relié

Le disque du serveur ne suffit pas à une campagne. Une fois le site relié à
sDrive (onglet **Hébergement**, bouton « Relier le site à sDrive », puis « Parcourir
sDrive » pour choisir le dossier) et le numéro réglé, chaque notice envoyée
range ses fichiers dans `<dossier>/<numéro>/originaux/<rubrique>/<Commune>/`
(`Caen_2` pour une deuxième notice de Caen dans la même rubrique), par exemple
`AM99/originaux/IV_Sepultures_et_necropoles/Caen/`. La rubrique V se partage
en ses sous-rubriques :
`V_Installations_artisanales/B_Carrieres_mines_et_metallurgie/Caen/`. Le
dossier se fixe au premier rangement : une rubrique changée ensuite ne le
déplace pas, et les notices rangées avant la 3.75 restent directement sous
`originaux/`. On y range originaux, JPEG tirés par le navigateur,
autorisations, Word de chaque version et `a-deposer.txt`. Le Word porte la
commune et le nom de famille du responsable d'opération, `Caen_Dupont_v1.docx`,
pour qu'on le reconnaisse sorti du dossier ; ceux rangés avant la 3.74 gardent
leur nom `notice_v1.docx`. Les versions successives s'y gardent ; seule la
dernière va au fascicule.

Le rangement se fait par la file, après l'envoi : l'auteur n'attend pas. Le
serveur n'efface sa copie qu'après avoir relu sur sDrive la taille de chaque
fichier, et seulement si la figure garde un aperçu, dont vivent le Word, le
courriel et la fiche. Il ne garde donc que les aperçus. La fiche dit où la
notice est rangée, ou pourquoi elle ne l'est pas encore ; ses fichiers restent
alors sur le serveur, et rien n'est perdu. Un passage coupé par le serveur,
faute de temps ou de mémoire, compte comme un essai manqué : une notice trop
lourde ne retient pas les suivantes.

Une panne de sDrive n'est la faute d'aucune notice, et n'en use pas les
essais : sDrive injoignable, tombé en cours de passage, ou un compte plein
(507), le passage s'arrête et reprend un quart d'heure plus tard, une heure
pour un compte plein. Après trois refus de suite du mot de passe (401), le
rangement cesse d'essayer : une liaison révoquée ne revient pas d'elle-même,
et Nextcloud finirait par bloquer le serveur. Un bandeau le dit ; relier de
nouveau le site, ou « Ranger maintenant », le relance. Les notices laissées
après leurs huit essais ont aussi leur bandeau.

La fiche, la correction et l'export rapatrient de sDrive, le temps de s'en
servir, ce qui n'est plus sur le serveur. Supprimer une notice n'efface pas
ce qui est rangé sur sDrive.

L'onglet Hébergement dit où vont les envois et l'export, combien de notices
attendent d'être rangées, et où en est l'export de chaque rubrique.

### La place prise

L'onglet **Stockage** dit ce que prennent les notices, catégorie par
catégorie :

- le site entier, rapporté au plafond de l'hébergeur (2 Go), et la part du
  dossier des notices ;
- le dossier des notices : originaux, aperçus, JPEG tirés des TIFF,
  autorisations de reproduction, feuille de styles, et les fichiers
  qu'aucune notice ne désigne plus ;
- les fichiers de passage (Word, ateliers d'export, copies ramenées de
  sDrive), en disant si leur répertoire compte dans le quota ;
- ce qui est rangé sur sDrive, d'après la table de chaque notice ;
- la base de données : saisies, versions antérieures, termes, réglages ;
- les dix notices les plus lourdes sur le serveur.

Un fichier se classe d'après la notice qui le désigne, non d'après son nom,
tiré au sort. Les fichiers se comptent à l'ouverture de l'onglet, en huit
secondes au plus ; au-delà, les chiffres sont donnés pour des minimums.

### Purger les notices

Depuis la 3.103, le bas de l'onglet **Stockage** vide la Chronique de ses
notices — après les essais, ou d'un numéro à l'autre. En deux temps, chacun
demandé par un mot tapé en capitales, puis confirmé :

1. **Mettre toutes les notices à la corbeille** (taper `PURGER`). Seules
   partent les notices reçues quand la page s'est affichée : une notice
   déposée entre-temps reste. De la corbeille, une notice se remet d'un
   clic depuis la liste des notices, et redevient privée ;
2. **Effacer les notices de la corbeille** (taper `EFFACER`), sans retour,
   leurs fichiers avec elles. WordPress vide aussi la corbeille de lui-même
   au bout de trente jours.

Ce que la purge laisse et ce qu'elle vide :

- **l'annuaire des auteurs n'est pas touché** : les auteurs des notices
  purgées y restent, avec la référence de leurs notices, et passent dans
  les deux exports. Une notice reçue plus tard complète leur ligne et, s'il
  a changé, met à jour leur nom ou leur institution ;
- la file d'export vers sDrive est vidée : elle n'aurait plus rien à
  copier ;
- les dossiers rangés sur sDrive restent tels quels ; ils se suppriment
  là-bas, si on le veut. Leurs noms restent pris, pour qu'une nouvelle
  notice de la même commune n'aille pas dans l'ancien dossier. Une case de
  l'effacement, décochée d'office, les rend libres : à ne cocher qu'une fois
  les dossiers supprimés de sDrive.

Pendant une purge, le rangement, l'export et le dépôt des relectures sur
sDrive s'arrêtent ; si l'un d'eux travaille, la purge ne fait rien et le
dit. Une purge s'accorde une minute : si les notices sont trop nombreuses,
elle dit combien restent, et un second clic les emporte. Sans corbeille sur
le site (`EMPTY_TRASH_DAYS` à 0), elle refuse : les notices seraient
effacées sans retour.

### L'annuaire des auteurs

L'onglet **Annuaire** réunit les personnes nommées dans les notices reçues :
le responsable d'opération, et pour les notices anciennes le co-responsable
et les co-auteurs. Une ligne par adresse, classée par nom puis par prénom,
sans égard aux accents ni aux capitales ; le nom et l'institution sont ceux
de la notice la plus récente. Les notices qu'une correction remplace ne
comptent pas. Qui dépose pour le responsable y figure comme déposant ;
avant la version 3.81, il ne laissait que son adresse, et sa ligne est sans
nom.

Chaque ligne porte un bouton **Retirer** — qui ne veut plus d'envois, une
adresse morte. La personne sort de l'onglet et des deux exports ; **ses
notices ne changent pas**. L'adresse retirée est notée à part
(`na_annuaire_retires`), avec le nom qu'elle portait, et reste écartée même
si elle revient dans une nouvelle notice. Les retirées se listent sous le
tableau, chacune avec un bouton **Rétablir** et un bouton **Supprimer
définitivement**. Ce dernier, après confirmation, ôte la ligne des
retirées sans retour : l'adresse reste écartée pour de bon, et l'on n'en
garde qu'une empreinte (`na_annuaire_supprimes`), ni l'adresse, ni le nom,
ni la date. Seule une adresse déjà retirée se supprime.

Ni une purge ni la suppression d'une notice ne vident l'annuaire : avant
qu'une notice parte à la corbeille, ou s'efface sans y passer, ses auteurs
sont gardés à part (`na_annuaire_gardees`), avec le nom, l'institution, les
rôles et la référence de leurs notices. Une notice d'essai de charge, ou
qu'une correction remplace, n'y laisse rien. On ôte quelqu'un de l'annuaire
en le retirant, puis en le supprimant définitivement : une adresse
supprimée n'y reste pas ; l'outil d'effacement des données personnelles de
WordPress en ôte aussi la ligne.

Deux boutons l'emportent :

- **pour la liste de diffusion** : un fichier texte, une ligne par personne,
  `adresse Prénom Nom`. C'est la forme que Sympa (listes.services.cnrs.fr)
  accepte dans l'ajout de plusieurs abonnés ; on y colle le contenu ;
- **en tableur** : Nom, Prénom, Courriel, Institution, Rôle, Notices, en
  CSV séparé par des points-virgules.

Les adresses ne se voient et ne sortent que pour qui règle l'extension.
Elles ont été données pour l'instruction des notices : le premier envoi à
la liste gagne à dire pourquoi l'on y est inscrit et comment en sortir.

## Ce qui se passe quand ça ne passe pas

**La saisie n'est jamais perdue.** Une notice se rédige en une heure : la
perdre parce qu'on a oublié la vérification anti-robot, ou parce que la
page avait expiré pendant qu'on écrivait, est le défaut le plus coûteux qu'un
formulaire puisse avoir. Tout refus met donc la saisie de côté et le
formulaire se remplit de lui-même au retour — champs, cases cochées, mots-clés
Pactols, texte de la notice.

Les fichiers font exception : aucun navigateur ne permet de regarnir un champ
de fichier, il faut les redéposer. Ce qu'on avait écrit à leur sujet — titre,
texte alternatif, légende, crédits — revient dès qu'ils sont rechoisis, dans le
même ordre. Une autorisation de reproduction ne revient pas plus qu'un
fichier : la figure dit qu'elle est à joindre de nouveau.

La reprise vit une heure après un refus.

### Une connexion qui flanche pendant l'envoi

Depuis la 3.104, la page envoie la notice elle-même. Une barre dit ce qui
est parti, en pourcentage et en mégaoctets ; une fois les fichiers arrivés,
elle dit que le serveur enregistre la notice. Avant, une connexion qui
flanchait pendant le téléversement d'une figure lourde laissait une page
blanche (« Request Time-out »), sans dire si la notice était partie.

Si la connexion se coupe :

- la page reste telle quelle, saisie et fichiers compris ;
- elle demande au serveur s'il a reçu la notice. S'il l'a reçue, la
  confirmation s'affiche. Si elle est en train d'être enregistrée, la page
  attend ;
- si rien n'est arrivé, elle renvoie une fois d'elle-même. Si cela échoue
  encore, elle dit de vérifier la connexion et de cliquer de nouveau.

Chaque page tire un jeton, le même à chaque renvoi. Le serveur retient
sous ce jeton que la notice est en route, puis ce qu'elle est devenue :
un renvoi ne fait jamais de seconde notice. Un envoi trop lourd pour le
serveur laisse lui aussi la page en place, fichiers compris, pour qu'on
en retire un. Un navigateur trop ancien pour envoyer lui-même envoie
comme avant.

## Corriger un dépôt déjà envoyé

C'est en recevant sa copie qu'on voit ce qu'on n'a pas vu en saisissant. La
copie porte donc un lien qui rouvre le formulaire rempli de la même saisie ;
il vit un mois.

**La correction s'écrit dans la notice reçue.** Elle garde sa référence et sa
fiche ; il n'y a plus deux dépôts dont l'un est à supprimer. Les figures déjà
déposées reviennent dans le formulaire, chacune avec ses textes : on la garde,
on la remplace par un nouveau fichier, ou on la retire. Les autorisations de
reproduction de même. « Remplacer » sans fichier choisi est refusé, au
navigateur comme au serveur. De nouvelles figures s'ajoutent à la suite. La
rédaction reçoit de nouveau la notice, l'objet du courriel ouvert par
`[CORRIGÉE]`, avec la même référence.

**La rédaction corrige par le même formulaire.** La fiche porte un lien
« Corriger dans le formulaire » qui rouvre le formulaire rempli : on ajoute un mot-clé, on
reprend une légende, et le Word se refait de lui-même. Ce lien ne vaut que
deux heures, et que pour l'administratrice qui l'a ouvert ; il n'y a ni
vérification anti-robot, ni quota, ni courriel à l'auteur. Le même lien ouvre le courriel que
la rédaction reçoit, dans un cadre gris ; dessous, depuis la version 3.73,
« Écrire aux responsables de la notice » ouvre un message adressé à toutes les
adresses de la notice — responsable, correspondant, coauteur, déposant —, l'objet
déjà rempli.

Les liens de correction mènent à **la dernière page publiée qui porte le
formulaire** (`[notice_archeomed_pactols]`). Jusqu'à la version 3.72, c'était la
première : une page d'essai plus ancienne, restée en ligne, prenait sa place.
Une page d'essai qu'on n'utilise plus se met en brouillon — après un mois, quand
les liens déjà envoyés vers elle ont expiré.

**Deux corrections de la même notice ne s'écrasent pas.** Chaque notice porte
un numéro de version, que la page lit en s'ouvrant. Une correction ne passe
que si la notice porte encore ce numéro, et l'avance d'un : de deux pages
ouvertes sur la même version, la seconde est refusée. Son auteur lit quand la
notice a changé, par qui (« la rédaction », pour un auteur) et sur quels
points ; le formulaire garde sa saisie, et il renvoie après relecture. La
fiche garde l'histoire des vingt dernières corrections.

**Un lien qui n'est plus valable ne dépose rien.** Expiré, ou la notice
retirée entre-temps : le formulaire reprend la saisie pour qu'on puisse la
copier, mais n'a plus de bouton d'envoi.

### Les liens d'avant la version 3.69

Une notice inscrite avant porte un lien qui dépose une notice neuve. Il
fonctionne toujours ainsi pendant son mois : les illustrations sont à
redéposer, et leurs textes attendent leur fichier.

Une telle correction qui porte moins de figures, ou moins d'autorisations de
reproduction, que la notice qu'elle remplace le dit à la rédaction, dans le
Word (« à supprimer ») et en tête du courriel : les fichiers restent dans la
fiche de la notice remplacée, à ne pas supprimer avant de les avoir repris.

Rien ne distinguerait ce dépôt neuf du premier, et la rédaction garderait les
deux. Trois marques l'en empêchent :

- l'objet du courriel porte `[CORRECTION de XXXXXX]` ;
- le corps du courriel s'ouvre sur un bandeau rouge nommant le dépôt à
  supprimer ;
- le document Word s'ouvre sur le même avis, encadré comme le sont les
  figures. Il ne paraît pas dans un fascicule, où la question ne se pose plus.

La fiche d'administration porte en outre la ligne « Remplace la notice ».

**La référence remplacée ne se lit pas dans le formulaire.** Un champ caché se
forge, et l'on ferait dire à la rédaction d'effacer n'importe quelle notice :
le formulaire ne renvoie que le jeton par lequel il a été rouvert, et c'est la
réserve — écrite par le plugin — qui dit ce qu'il remplace. Le jeton s'efface
dès qu'il a servi, pour qu'une troisième notice ne vienne pas corriger la
première.


Un refus n'est plus un écran unique : l'auteur lit ce qu'on lui reproche —
une page expirée, un renseignement manquant qu'on nomme, une vérification
anti-robot à refaire, un fichier écarté, un quota atteint. Sans cela, il
recommençait à l'identique.

Deux plafonds distincts, et non plus un :

| Compteur | Par heure | Ce qu'il compte |
|---|---|---|
| Tentatives | 300 par adresse IP | toutes les soumissions, abouties ou non |
| Envois | 15 par adresse IP | seulement ce qui est réellement parti (filtre `notice_archeomed_plafond_par_poste`) |
| Envois | 25 par adresse électronique | idem, mais là une personne est vraiment désignée |
| Réserves de saisie | 10 par adresse IP | les saisies gardées après un refus |

Les compteurs sont horaires : chacun se range sous l'heure en cours
(`na_…_AAAAMMJJHH`). Leur échéance repartait à chaque dépôt, et un plafond « par
heure » ne retombait jamais tant qu'on déposait.

**Le disque.** Un dépôt n'écrit ses fichiers que s'il reste de la place. Au-delà
de 85 % du plafond de l'hébergeur, ou de 500 Mo reçus dans l'heure (filtre
`notice_archeomed_poids_par_heure`), la notice est reçue sans ses fichiers :
chaque figure devient « envoyée à part », son fichier témoin le dit à la
rédaction, et la page de fin demande à l'auteur d'envoyer ses figures et ses
autorisations. La part du disque s'estime sans le parcourir (la dernière mesure,
plus ce que les dépôts ont reçu depuis) ; la mesure se refait à part. Un bandeau
d'administration prévient dès 60 %.

**Rien n'est effacé tant que rien n'est parti.** Le fichier stylé et les
illustrations vivent dans le répertoire temporaire du serveur sous un nom
portant `notice-archeomed-garde-`, à l'abri de la purge quotidienne, et ne
sont supprimés qu'une fois le courriel de la rédaction remis.

## La relecture par les coordinateurs de rubrique

Chaque coordinateur relisait sa partie dans un Word stylé, et en cassait
souvent les styles : une métadonnée ajoutée à la main, le fichier ouvert dans
un autre traitement de texte. La relecture se fait désormais sur le site,
dans un écran qui écrit dans la saisie gardée — celle d'où se refont le Word,
le fascicule et le dossier Métopes.

**L'écran vit sur une page publique, dans le thème.** Comme le formulaire de
dépôt, il se pose par un shortcode, `[relecture_archeomed]`, sur une page
qu'on publie ; l'onglet « Relecture » des réglages donne l'adresse de cette
page, ou dit qu'aucune ne porte le shortcode. La page ne se met pas en cache.
Sans connexion, elle renvoie à la connexion, qui ramène à elle ; un compte
sans droit n'y lit qu'une phrase.

**Un coordinateur n'entre pas dans l'administration.** Il se connecte, puis
tout se passe sur cette page : pas de barre d'administration, la connexion
le mène à la page, et toute adresse de `wp-admin` l'y renvoie — sauf
`admin-ajax.php` et `admin-post.php`, que le verrou et les gestes de l'écran
emploient. Il ne peut donc pas ouvrir son profil : un mot de passe oublié se
refait par le lien de la page de connexion. La direction de la revue relit
sur la même page ; le menu « Relecture » de l'administration donne l'adresse
de la page, et de quoi la voir comme un coordinateur (ci-dessous).

**Elle s'ouvre quand la campagne est close.** Rien ne fermait une campagne :
l'onglet « Relecture » des réglages porte donc une case « La campagne est
close ». Cochée, elle ouvre la relecture et ferme les liens de correction des
auteurs : qui en suit un lit que la campagne est close et qu'il faut écrire à
la rédaction ; un formulaire de correction resté ouvert n'enregistre plus
rien. Le lien « Corriger cette notice » du courriel reçu par la rédaction,
lui, marche avant comme après la clôture. Le dépôt de nouvelles notices n'est pas fermé par cette case : il
l'est en dépubliant la page du formulaire.

**Qui relit quoi.** Deux rôles WordPress, créés par l'extension :

- « Coordinateur de rubrique (Chronique) » : lire son profil et relire
  (`na_relire`), rien d'autre. L'onglet « Relecture » associe un ou plusieurs
  coordinateurs à chaque rubrique ;
- « Direction de la revue » : la capacité `na_relire_tout`, qui ouvre toutes
  les rubriques avec le même écran, et `na_regler`, qui ouvre l'extension
  entière : le menu « Chronique » avec ses deux sous-menus, « Notices
  reçues » (la liste, les fiches, les téléchargements, les relances) et
  « Réglages » (tous les onglets, Annuaire compris). C'est tout : ni
  articles, ni pages, ni comptes, ni réglages du site. La direction a dans
  l'administration le menu « Chronique » et l'entrée « Relecture » ; toute
  autre page, tableau de bord et profil compris, la renvoie aux notices
  reçues, et la connexion la mène à la page de relecture. Les
  administrateurs du site ont tout cela d'office, par `manage_options`.

Le rôle « Rédaction de la Chronique (relecture) » des versions antérieures à
la 3.85 disparaît à la mise à jour : ses comptes passent à la direction.

Il n'y a pas de lien anonyme. Le droit se
juge à chaque requête sur la notice elle-même — sa rubrique en base —, jamais
sur ce que l'adresse ou le formulaire prétendent.

**Voir la relecture comme un coordinateur.** La direction et les
administrateurs peuvent voir la page telle qu'un coordinateur la verra, pour
préparer la relecture ou répondre à ses questions. L'entrée « Relecture » du
menu propose de choisir soit un coordinateur nommé, parmi les comptes que
l'onglet « Relecture » affecte à une rubrique — on voit alors toutes ses
rubriques —, soit une rubrique, même si personne n'y est encore affecté ; le
bouton « Voir la relecture comme lui » mène à la page de relecture.

L'entrée « Relecture » salue d'abord chacun par son prénom (à défaut, par le
nom qu'affiche son compte) et donne en grand le lien de la page : « l'ensemble
des notices » pour la direction, les notices de ses rubriques pour un
coordinateur. Le choix du regard vient dessous, dans un cadre plus discret,
sous le titre « Voir le rôle de chacun ».

Tout s'y calcule avec les droits du coordinateur : son sommaire, ses notices
et ses renvois, ce qu'il corrige et ce qu'il lit seulement, les états, les
boutons et les messages. Une adresse forgée n'ouvre rien de plus qu'à lui.
Mais rien ne s'y enregistre : les champs et les boutons restent à leur place,
désactivés ; la notice n'est pas verrouillée ; rien n'entre au journal ;
chaque geste envoyé malgré tout est refusé par le serveur. Un bandeau dit qui
l'on regarde — « Vous voyez la relecture comme Jeanne Martin (coordination de
la rubrique II). Rien de ce que vous faites ici ne s'enregistre. » — avec un
bouton « Revenir à ma vue ».

Avant la clôture de la campagne, la vue montre la relecture comme si elle
était close, et le bandeau ajoute qu'aujourd'hui ce coordinateur ne voit
encore aucune notice. La direction elle-même, avant la clôture, ne voit sur
la page que « La relecture s'ouvrira quand la campagne sera close » : elle
lit les notices dans « Notices reçues ».

Le choix ne vaut que pour le compte qui l'a fait, deux heures au plus, et il
est revérifié à chaque page : un compte qui ne relit pas tout ne peut pas
s'en servir, et le regard d'un compte qui ne coordonne plus de rubrique
s'efface. L'administration, sa barre et la connexion n'en sont pas changées.
Pendant la vue, une notice que l'on tiendrait ouverte dans un autre onglet,
sous son propre compte, ne s'enregistre pas : revenir d'abord à sa vue.

**Ce que voit le coordinateur.** Les notices de sa rubrique, dans l'ordre du
fascicule — sous-rubriques, puis communes —, avec un sommaire latéral qui
dit l'état de chacune et qui la tient ouverte. C'est le tri du fascicule
lui-même (`ranger_comme_au_fascicule()`), non un second tri écrit à côté.

**Le formulaire** reprend celui du dépôt, sans ses explications. Le texte et
les champs se corrigent. Les lieux du titre se cherchent dans Pactols depuis
le navigateur, comme au dépôt : une commune changée prend l'identifiant (ARK)
du terme choisi, un nom inchangé garde le sien — et le dossier de la notice
sur sDrive garde son nom, on ne le renomme pas. Comme au dépôt, la première
commune choisie remplit la précision géographique d'après Pactols (« vérifiez »),
et « Falaise (Calvados) » s'allège en « Falaise » quand la parenthèse dit
déjà « Calvados ». Trois choses ne s'y font pas,
et le serveur les refuse quoi que poste la requête :

- les **courriels des responsables** ne sont pas dans la page d'un
  coordinateur — pas même masqués : le serveur ne les y écrit pas. La
  direction les lit, sans champ pour les changer ;
- une **illustration** ne s'ajoute ni ne se remplace. Elle se retire, avec un
  motif obligatoire (redondante, qualité insuffisante, autorisation manquante,
  au-delà du nombre admis par les normes, ou « autre » avec une précision).
  Retirée, elle n'est que masquée : ses fichiers et ses textes restent, à son
  rang, et la direction la rétablit d'une case. Le fascicule, le dossier
  Métopes et le Word de relecture ne la portent plus, et les figures qui
  restent se numérotent à la suite : un rappel invite alors à vérifier les
  appels « (fig. N) » du texte, avec ce que le contrôle des appels y trouve ;
- un **mot-clé** se retire, ou s'ajoute en le choisissant dans la liste que
  Pactols propose. Un terme neuf sans identifiant n'entre pas.

L'aperçu de chaque figure est celui qui a été fabriqué pour le Word : il
reste sur le site quand l'original part sur sDrive (le rangement n'efface un
original que si son aperçu se lit). Une figure sans aperçu — envoyée à part,
ou que le serveur n'a pas su réduire — le dit, sans image.
Un clic sur l'aperçu l'agrandit dans une fenêtre par-dessus la page, à la
taille de l'écran : c'est sur l'image entière qu'on décide d'un retrait.
Échap, le bouton « Fermer » ou un clic à côté la referment.

Au-dessus du texte, le titre de la notice se lit tel qu'il paraîtra :
« CAEN (Calvados), Château », avec la ponctuation et l'italique du lieu-dit
que règlent les normes. Il ne se saisit pas : il suit les champs du lieu, et
change avec eux. La sous-rubrique (A, B, C) ne se montre que pour une notice de la
rubrique V, comme au dépôt.

Un coordinateur ne voit que les rubriques que les réglages lui confient, et
les notices qui y renvoient.

Deux boutons : « Enregistrer », qui reste sur la notice, et « Suivante », qui
enregistre puis ouvre la notice suivante du sommaire.

**Ce que voit la direction.** Toutes les notices, et l'écran le lui
rappelle. Le sommaire est celui du fascicule entier : chaque rubrique y est
repliée sous un triangle, avec le compte de ses notices validées (« 3
validées sur 12 ») ; un clic la déplie. La rubrique de la notice ouverte est
dépliée d'elle-même, et une rubrique dont toutes les notices sont validées
passe au vert. Un troisième bouton, « Valider », enregistre la notice, la
marque validée et ouvre la suivante — à la fin d'une rubrique, la première
de la rubrique d'après.

**La notice validée est la version définitive.** Elle ne se corrige plus :
ni par l'écran, ni par le lien « Corriger cette notice » du courriel, ni
depuis sa fiche, et le serveur refuse tout enregistrement qui la changerait.
Un coordinateur peut encore lui laisser une remarque. Pour la reprendre, la
direction la **rouvre** d'un bouton, avec un motif : la notice retrouve
l'état qu'elle avait avant la validation, et la réouverture reste au
journal, avec qui, quand et pourquoi. Il faut ensuite la valider de nouveau.

**Le verrou.** Une notice ouverte est verrouillée pour les autres, au nom de
qui l'a ouverte. Le verrou est pris par une écriture conditionnelle, se
renouvelle chaque minute tant que la page est ouverte, se rend quand on la
quitte, et expire seul au bout de cinq minutes sans nouvelles. Le dernier
rempart reste le numéro de version : deux enregistrements partis de la même
version ne s'écrasent pas, le second est rendu au conflit.

**Les états.** À relire ; relue par le coordinateur ; à soumettre à
l'auteur ; soumise à l'auteur ; accord reçu ; validée par la direction. Le
coordinateur marque une notice « relue », ou coche « modification de fond »,
qui la fait passer à « à soumettre à l'auteur » : une correction de forme
passe sans accord, une modification de fond demande l'accord écrit de
l'auteur. Tout le reste relève de la direction ; la validation ne se fait
que par le bouton « Valider », et la réouverture que par « Rouvrir la
notice ». À partir de « soumise à l'auteur », le coordinateur ne corrige plus.

**Aucun courriel ne part de lui-même vers l'auteur.** La direction voit la
notice « à soumettre », et un bouton « Soumettre à l'auteur » envoie au
responsable un courriel avec le Word où se lisent toutes les modifications
depuis sa version. Un courriel refusé ne change ni la notice ni son état : on
recommence. Le serveur refuse l'envoi pour toute notice qui n'est pas « à
soumettre à l'auteur » : rien ne part sans modification de fond, même par
une requête rejouée.

**La remarque à la direction** est un champ à part, hors de la saisie, donc
hors de tout ce qui se publie. Chaque remarque reste au journal, datée et
signée.

**Le journal** de chaque notice dit qui, quand, quoi : les champs corrigés,
les figures et les mots-clés retirés avec le motif, les renvois ajoutés ou
retirés, les changements d'état, les remarques. Il ne se récrit pas : chaque
ligne est une écriture de plus, et rien dans l'écran n'en modifie.

**Une notice à cheval sur deux rubriques.** Seul le coordinateur de la
rubrique principale la corrige ; il peut ajouter un renvoi, dans les deux
emplacements existants, pourvu qu'il ne vise ni la rubrique de la notice ni
une rubrique déjà visée, et retirer n'importe lequel. Le coordinateur d'une
rubrique de renvoi voit la notice en lecture seule, dans un bloc à part de
son sommaire, dès que le renvoi est posé ; il peut laisser une remarque à la
direction, et retirer le renvoi vers sa propre rubrique. Changer la rubrique
principale reste à la direction.

**Les versions.** Chaque enregistrement garde la version précédente
(`_na_relecture_version`, une ligne par version, avec la date et l'auteur) et
paraît aussi dans l'historique de la fiche. De deux versions gardées se tire
un **Word à suivi de modifications** : des `w:ins` et des `w:del` signés du
relecteur, mot par mot. Il se télécharge depuis l'écran — sans les adresses
des responsables pour un coordinateur — et se dépose dans le dossier de la
notice sur sDrive sous le nom `Caen_Dupont_v3_relecture_Jeanne_Martin.docx`,
à côté des `Caen_Dupont_v1.docx` du rangement, par une tâche à part
(`na_deposer_les_relectures`) : sDrive en panne, la correction est
enregistrée quand même et le Word attend. Il ne porte aucune image.

**Le fascicule reste vierge de tout suivi** : il se fabrique, comme avant, de
la dernière version de chaque notice. La version corrigée par le coordinateur
est la dernière avant la relecture de la direction, qui corrige ensuite avec
le même écran ; ses enregistrements sont versionnés de la même façon. La
version que la direction valide est la version définitive.

## Tenir la charge : accepter vite, expédier ensuite

C'est le point qui compte pour une campagne où l'on sollicite des milliers de
personnes : elles répondent en même temps.

La requête de l'auteur ne fait que vérifier, ranger les fichiers, fabriquer
le document et **inscrire** la notice — une fraction de seconde. Les
courriels partent ensuite, par paquets de cinq, sous le planificateur de
WordPress. Sans cela, un processus PHP restait pris une trentaine de secondes
par notice, le temps du transfert des pièces jointes ; un hébergement
mutualisé en compte cinq ou dix, et le site entier cessait de répondre au
dixième dépôt simultané.

Le menu **Notices Archéomed** liste ce qui a été reçu et l'état de chacune :

| État | Ce qu'il veut dire |
|---|---|
| en attente | inscrite, pas encore expédiée |
| en cours d'envoi | un passage du planificateur s'en occupe |
| envoyée | la rédaction l'a reçue |
| en échec | cinq tentatives sans succès — à reprendre à la main |

## L'ordre du volume

Les notices se rangent d'elles-mêmes comme la Chronique se monte :

1. **rubrique principale** (I à VII),
2. dans la rubrique V seulement, **matière** : A – Céramique, terres cuites
   architecturales, verrerie ; B – Carrières, mines et métallurgie ; C – Autres
   installations artisanales,
3. **famille d'opération** — opérations de terrain, prospections, projets
   collectifs de recherche,
4. **commune**, par ordre alphabétique.

La rubrique I s'appelle désormais « I. Constructions et habitats civils –
Environnement rural et urbain ». Une notice reçue sous l'ancien libellé reste
dans la rubrique sans rien réécrire en base : une table d'équivalence le lit
comme le nouveau partout où une rubrique se compare (fascicule, dossier,
renvois, filtre et comptes de la liste, lien de correction), et le Word, le
fascicule et le courriel impriment le nouveau.

Dans la rubrique V, l'auteur choisit la **sous-rubrique** (A, B ou C), selon ce
que l'atelier fabriquait ou exploitait ; le formulaire, la relecture et le courriel
l'appellent ainsi depuis la version 3.73, « matière » auparavant. Le champ
paraît quand la rubrique V est choisie, et n'est obligatoire que pour elle. Les
sous-rubriques s'écrivent « V. A1. – Céramique, terres cuites architecturales,
verrerie : opération de terrain », « V. A2. – … : prospections », « V. A3. – … :
projets collectifs de recherche », de même pour B et C. Une notice V d'avant les
matières garde « V. 1. », se range en tête, et porte la ligne « à supprimer »
« Sous-rubrique à choisir (A, B ou C) ». Un renvoi vers la rubrique V se range selon la
matière de sa notice — qui n'en a pas, venant d'une autre rubrique : il se
range alors comme une notice sans matière.

La famille ne se demande pas à l'auteur : elle se déduit de la nature qu'il a
cochée. Une notice qui en coche plusieurs prend la première rencontrée dans
cet ordre — une fouille accompagnée d'une prospection reste une fouille.

**Le fascicule d'une rubrique se télécharge d'un clic**, au-dessus de la
liste : toutes ses notices dans un seul document Word, dans cet ordre-là,
prêtes à relire d'un bloc. C'est la pièce qui remplace quarante courriels
ouverts un à un.

Le document d'une notice se retélécharge de même, depuis sa fiche. Il n'est
pas archivé : il est **refabriqué** à partir de la saisie, qui est gardée. Il
ne peut donc pas se perdre dans une purge, et il suit d'office une évolution
de la feuille de styles Métopes — ce qu'un fichier archivé ne ferait pas.

## La liste

La liste se **trie** par rubrique, par responsable ou par état, et se **filtre**
par rubrique. Ouvrir une
notice donne sa fiche : le texte tel que la rédaction l'a reçu, et à côté le
suivi (état, référence, responsable, dates, pièces jointes encore présentes).
Les encarts des autres extensions sont écartés de cet écran : une notice reçue
n'est ni un article ni un produit.

Des vues donnent d'un clic ce qui attend un geste : **À traiter**, **En
échec**, **En file d'envoi**, **Envoyées**. L'état de chaque notice se dit en
mots, avec la raison d'un échec traduite en clair ; la réponse exacte du
serveur reste dans l'encart « Dépannage » de la fiche. Une notice qu'une
correction remplace le dit, et ne paraît plus au fascicule ni au dossier.
Les fascicules et dossiers Métopes se téléchargent rubrique par rubrique, un
seul à la fois : un second clic pendant l'assemblage est refusé poliment.

**Quand le site est relié à sDrive**, le dossier Métopes ne se télécharge
plus : « Exporter sur sDrive » l'écrit dans `<numéro>/` à côté des originaux,
avec `style/` (le Word), `icono/hr`, `icono/br`, `XML/indexation`, la page de
relecture et le lisez-moi. Le Word appelle `../icono/br/<nom>.jpg` : une
figure porte le même nom partout. L'export passe par la file, une figure à
la fois, pour tenir dans le temps qu'accorde l'hébergement ; l'état, sous le
lien, se met à jour seul. Deux exports demandés ensemble passent l'un après
l'autre. Une figure envoyée à part est reprise si son original a été déposé ;
sinon le lisez-moi la donne à reprendre.

Un nouvel export d'une rubrique efface d'abord ce que le précédent avait
écrit pour elle, et rien d'autre : les rubriques partagent `icono/` et
`style/`, et chacune tient la liste de ses fichiers. L'export ne touche
jamais à `originaux/`, à `indd/`, ni au reste de `XML/`.

Une notice qui n'a pas pu partir se retente d'elle-même, cinq fois, en
espaçant les essais. Si la file s'allonge sans redescendre, c'est que le
planificateur de WordPress ne tourne pas sur cet hébergement : un bandeau le
signale dans l'administration, avec un bouton **Expédier maintenant**, et
l'on peut alors repasser en envoi immédiat depuis la page de réglages.

### Ce qui n'est plus compté par adresse IP

Une adresse IP ne désigne pas une personne : l'Inrap, une Drac, une
université sortent tous par la même. Compter serré revenait à bloquer un
institut entier dès le sixième dépôt, un jour de campagne. La barrière contre
les robots est Turnstile ; les compteurs ne sont qu'un garde-fou contre le
martèlement, et seul celui de l'adresse électronique reste serré.

## Les normes éditoriales

Ce que la revue a décidé pour ses notices est rangé dans **Chronique ▸
Réglages ▸ Normes éditoriales**, et le code ne lit plus que cet onglet.
Chaque norme y porte ses variantes, un exemple pour chacune, et le choix de
la revue coché d'avance et marqué comme tel ; « Rétablir les normes de la
revue » les remet toutes d'un geste. Une autre revue adapte ainsi le
formulaire à ses propres usages sans toucher au code :

| Norme | Choix de la revue | Variantes |
|---|---|---|
| Le mot « siècle » après un siècle en chiffres | abrégé, « XIIe s. » | en toutes lettres ; tel que saisi |
| Les chiffres romains du siècle | petites capitales | capitales ; tels que saisis |
| L'ordinal du siècle | en exposant | sur la ligne |
| Corriger la typographie | oui | non |
| L'espace devant ; ! ? et dans les guillemets | insécable | fine insécable |
| Une opération sur plusieurs années | 2004-2005 | 2004–2005 ; 2004/2005 |
| Entre la parenthèse et le lieu-dit | un point | une virgule |
| Le lieu-dit du titre | en italique | en romain |
| L'appel d'une figure | Fig. | Figure ; Ill. |
| Entre le numéro et le titre d'une figure | une espace | un deux-points ; un point |
| Norme des photographies, des dessins au trait | 10 × 15 cm à 300 ppp ; 1 200 ppp | chiffres libres |
| Longueur recommandée du texte | 300 à 700 mots | chiffres libres |
| Texte alternatif : avis au-delà de | 150 caractères | de 20 à 300 |
| Sigles à développer à leur première mention | une liste de sigles courants | une ligne par sigle, « SIGLE = développement » |
| Les avis donnés au dépôt | tous | chacun se coche ou se décoche |

Les documents produits après un changement suivent les nouvelles normes —
un fascicule ou un dossier refabriqué aussi. L'année d'une opération sur
plusieurs années, elle, s'écrit au dépôt.

La correspondance des blocs avec les styles Métopes a son onglet à part,
**Styles Métopes** : voir plus bas.

## La typographie

Le texte imprimé du document reçoit la typographie française : espace
insécable devant la ponctuation double et à l'intérieur des guillemets (jamais
d'espace fine, qui devient insécable), apostrophe courbe, points de
suspension, insécables dans les nombres et avant les unités (« 10 000 m »,
« 50 % »), dans « p. 12 », « fig. 3 », « XIIe s. », « av. J.-C. », « n° 3 »,
et entre une initiale et son nom. Les adresses, les heures (« 14:30 ») et les
titres anglais cités (« Weapons of the Weak: … ») sont laissés tels quels.
Chaque fragment se corrige en voyant ses voisins : un deux-points qui suit un
mot en italique reçoit bien son insécable. Les noms du bloc de responsabilité
gardent leur espace ordinaire entre prénom et nom, que la chaîne coupe.

**« siècle » s'abrège toujours « s. »** après un siècle en chiffres : « au
XIIe siècle » s'imprime « au XIIe s. », « aux XIIe et XIIIe siècles. » garde un
seul point ; « ce siècle » ne bouge pas. Les blocs d'index gardent, eux, la
forme du thésaurus.

**Les siècles sortent en petites capitales**, l'ordinal en exposant : « xii »
en petites capitales puis « e » en exposant, forme que la chaîne rend en
`<hi rend="small-caps">`. L'auteur peut les taper en capitales (« XIIe s. »),
en bas de casse (« xiie siècle », quand les petites capitales se sont perdues
au collage) ou l'ordinal en exposant : tous se reconnaissent à ce qui les
suit — « s. », « siècle », « millénaire », ou une suite « IIIe-IVe s. ». Un
chiffre romain sans siècle derrière (« la tour XII ») ne bouge pas, ni « ce
siècle ». Les termes Pactols de période et la page de relecture suivent la
même règle.

**Dans le HTML** — courriel à la rédaction, copie de l'auteur, fiche
d'administration, page de relecture —, le chiffre s'écrit en capitales
(« XII ») et c'est le style qui le réduit
(`font-variant: small-caps; font-variant-caps: all-small-caps`) : l'œil voit
des petites capitales, et un lecteur d'écran lit « XII », non le mot « xii »
(« vie » pour VI). Un client de courriel qui ignore ce style montre des
capitales. Le Word ne change pas : bas de casse sous `w:smallCaps`, comme
Métopes le veut.

Un corpus de cas, dans les essais, fixe ce que chaque règle doit rendre.

## Les contrôles du dépôt

Au dépôt, la notice est relue par des règles qui **avertissent sans jamais
refuser** : un texte qui s'arrête sans ponctuation (collage tronqué), une
ligne courte — moins de huit mots — sans ponctuation, qui est un intertitre
posé sans le bouton T1 ou une liste posée sans le bouton Liste à puces (depuis la 3.101 ; les lignes courtes qui
se suivent ne donnent qu'un avis, « Les paragraphes 2 à 4… »), un
paragraphe coupé en deux, un intertitre qui finit par un point, une figure appelée dans le texte qui n'est pas
jointe ou une figure jamais appelée, un appel écrit « (figure 3) » ou
« fig 3 » au lieu de « (fig. 3) » — le mot entier n'est relevé qu'entre
parenthèses, « la figure 3 montre » étant une phrase —, une photographie sous la norme annoncée
(10 × 15 cm à 300 ppp, soit 1 182 × 1 772 pixels, lus dans l'en-tête du
fichier), une figure sans crédits, « XIIème » pour « XIIe », une année tapée
« 1 250 », un nom en capitales, deux personnes
dans un même champ, une adresse dans le champ de l'institution, l'accessibilité
des figures (voir « Le texte alternatif »), un sigle non développé à sa
première mention.

**Les sigles.** La liste se règle dans **Normes éditoriales ▸
L'accessibilité** : une ligne par sigle, « SRA = service régional de
l'archéologie ». Une ligne qui commence par « # » est une note ; une ligne
mal formée est ignorée. La liste livrée porte les sigles courants de
l'archéologie française, à la forme de la revue : en capitales le sigle
qu'on épelle (« CNRS »), en bas de casse avec une capitale initiale celui
qu'on prononce (« Inrap », « Craham »). Ceux qui suivent la note sont à
confirmer. Un sigle est **développé** quand la phrase de sa première mention
porte aussi son développement — avant ou après lui, à la casse, aux accents
et aux articles près —, ou qu'il y est entre parenthèses (« service
archéologique de Normandie (SRA) »). Sinon : « Le sigle « SRA » n'est pas
développé à sa première mention : écrivez par exemple « service régional de
l'archéologie (SRA) ». » Les mentions suivantes ne comptent pas. Un sigle en
capitales se reconnaît à sa casse exacte (« US », non « us ») ; un sigle
prononcé, sous sa forme ou en capitales (« Inrap », « INRAP » ; non
« lidar », devenu nom commun). Les adresses web ne comptent pas. Un second
avis signale la casse fautive : « INRAP » pour « Inrap », « Cnrs » pour
« CNRS » (à partir de quatre lettres : « Us » ouvre une phrase). La rivière
« Drac » ne se distingue pas de la Drac : l'avis, qui ne bloque rien, peut
s'y tromper. L'aide du texte de la notice invite à développer chaque sigle à
sa première mention.

Ces avis s'affichent à l'auteur dans le formulaire, à la sortie du champ
(ceux du texte une seconde après la dernière frappe, y compris après un
collage ; une adresse électronique ou web mal formée dès qu'on quitte son
champ ; depuis la 3.100, un caractère qu'aucune adresse électronique ne porte
— « ; », « , », « : », une espace, un second « @ », « < », « > »,
parenthèses, crochets, guillemets droits — dès la frappe, l'adresse
seulement inachevée attendant toujours la sortie du champ), et
partent sous « À vérifier » dans le courriel, dans le document (style « à
supprimer ») et dans la page de relecture du dossier. La norme des
photographies est posée en un seul endroit, qui sert à l'aide et au contrôle.

Le lieu-dit prend une **capitale initiale** (au formulaire, à la sortie du
champ, et au serveur) : il ouvre la phrase du titre, après le point. Il perd le
point final qu'on y met par habitude, sauf abréviation ;
une opération sur plusieurs années s'écrit « 2004-2005 ».

## Le thésaurus Pactols

**Une période et un sujet au moins.** Depuis la version 3.73, la section
« Mots-clés » du formulaire n'est plus facultative : Périodes et Sujets
demandent chacun un terme, choisi dans la liste ou gardé tel quel — un terme
libre suffit, pour qu'une panne de Pactols ou du proxy n'empêche aucun dépôt.
« Autres lieux » peut rester vide. Le navigateur le dit sous la liste, et le
serveur le redemande. La rubrique de la Chronique, sa sous-rubrique pour la rubrique V
et les renvois ont quitté cette section : ils se choisissent dans la première,
juste avant la nature de l'opération.

Les recherches sont mises en réserve une journée : le même terme cherché deux
fois ne repart pas sur le réseau. Au-delà de deux cents interrogations par
heure et par connexion, le relais cesse de répondre — assez large pour qu'on
ne le voie jamais en remplissant un formulaire, assez étroit pour que le site
ne serve pas à marteler frantiq.fr.

**Un terme pris hors de la liste de Pactols ne sert pas.** L'auteur peut garder un
terme tapé hors de la liste (deux fois Entrée), mais le formulaire le prévient
qu'il ne servira pas. Le terme est marqué à la réception ; il sort de la ligne
de sa catégorie dans le Word et la page de relecture, et se lit en dessous,
« à supprimer » (« Terme pris hors de la liste de Pactols, non
retenu (mots-clés) : … »), pour
que la rédaction puisse en choisir l'équivalent. Le courriel le dit aussi. Les
termes sans identifiant d'une notice antérieure à cette marque restent tels
qu'ils étaient.

**Les candidats à Pactols** (Réglages ▸ Candidats à Pactols) réunissent ces
termes, relevés dans toutes les notices reçues à chaque ouverture de
l'onglet : regroupés par catégorie malgré la casse, les accents et les blancs,
avec leurs autres graphies, le nombre de notices et un lien vers chacune, et
la date du dernier dépôt. Les notices remplacées par une correction ne
comptent pas. La rédaction donne à chacun un statut — à proposer, proposé à
Frantiq, écarté — et une note (un équivalent, une date d'envoi) ; seuls ces
choix sont gardés (option `notice_archeomed_candidats`), la liste se refait
d'elle-même. Elle se télécharge en tableur (CSV, point-virgule, UTF-8), un
terme qui commencerait par « = » ne devenant pas une formule.

**La lecture des termes par le serveur** (Réglages ▸ Hébergement, sous
l'essai « Tester l'accès à Pactols ») se coupe quand le proxy de l'hébergement
refuse la sortie vers `pactols.frantiq.fr`. Les ARK restent sur les termes du
Word, en liens, et la transformation en XML peut en tirer les entrées d'index ;
seuls manquent la forme préférée et le fichier des blocs d'index du dossier.
Coupée, elle ne laisse plus de terme « non lu » dans le Word, et la tâche de
lecture cesse de réessayer. Rétablie, les notices se relisent à leur prochain
passage dans un fascicule ou un dossier.

## L'onglet Accessibilité

**Réglages ▸ Accessibilité.** En tête, **les illustrations des notices
reçues** : combien de figures sont à compléter (sans texte alternatif), à
vérifier ou en ordre, et la liste des notices concernées, chacune avec un
lien vers sa fiche. Les notices remplacées par une correction ne sont pas
comptées ; la saisie se lit par paquets de deux cents.

Plus bas, le bouton « Contrôler l'accessibilité du
formulaire » charge la page publiée du formulaire dans un cadre et la fait
passer, dans le navigateur de l'administrateur, au moteur libre axe-core
(4.10.2, règles `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`). Il affiche un
**score indicatif** — règles conformes / (conformes + en échec) —, les règles
en échec (gravité, nombre d'éléments, explication courte, et si l'élément vient
de l'extension ou du thème), les conformes, et ce qui reste à vérifier à la
main. Le dernier résultat se garde avec sa date (requête authentifiée, jeton,
droit `manage_options`). **Ce score n'est pas le taux de conformité RGAA** :
les tests automatiques ne couvrent qu'une partie des critères.

**Ce que le formulaire fait pour être accessible** (points pris en compte à la
conception, selon le RGAA 4.1 ; ils n'ont pas fait l'objet d'un audit complet) :

- `lang="fr"` sur le formulaire et dans les courriels HTML ;
- une **voie de secours** dans l'introduction : l'adresse de la rédaction, où
  envoyer la notice par courriel si le formulaire fait obstacle (lecteur
  d'écran, clavier…) — c'est aussi l'alternative quand la protection
  anti-robot ne peut se résoudre ; et, si l'onglet l'a, le lien de la
  déclaration d'accessibilité ;
- une limite de temps de vingt-quatre heures pour le puzzle (critère 13.1) ;
- les **avis** et les résultats vides de la recherche de lieux sont annoncés
  par la région `role=status` (critère 7.5), regroupés par rafale et jamais
  répétés ;
- le focus visible sur tous les champs, y compris dates et liens ; les cases à
  cocher à 24 px ;
- la barre de mise en forme de l'éditeur est un seul arrêt de tabulation, les
  flèches passent d'un bouton à l'autre ; l'aide rappelle Ctrl+B et Ctrl+I ;
- chaque liste de propositions porte le nom de son champ ; les champs
  facultatifs le disent, la sous-rubrique de la rubrique V est marquée obligatoire.

L'audit officiel se fait avec **Ara** (DINUM, libre et gratuit, RGAA 4.1,
<https://ara.numerique.gouv.fr/>), audit manuel dont le taux est celui qui
compte ; l'onglet en garde le taux, la date, le lien du rapport et l'adresse de la
page de la **déclaration d'accessibilité** — que l'introduction du formulaire
reprend en lien, avec l'adresse de secours pour envoyer sa notice par
courriel — et rappelle qu'un organisme public publie cette déclaration. Le script et le
style de l'onglet ne se chargent que sur lui, et le serveur n'appelle aucun
service.

## Journalisation

Les incidents (échec de génération, refus d'envoi, erreur de téléversement)
sont écrits dans le journal PHP avec le préfixe `Notice Archeomed:`. En cas
de comportement inattendu, c'est le premier endroit à consulter.

Les vingt derniers envois refusés se lisent aussi dans **Réglages ›
Hébergement**, sous « Derniers envois refusés ». Le tableau ne montre ni
adresse ni contenu, seulement :

- l'heure du refus ;
- le message vu par l'auteur ;
- la cause exacte, par exemple jeton refusé, champ piège rempli ou page
  affichée il y a plus d'une semaine ;
- l'âge de la page ;
- si la personne était connectée.

« La page a expiré » couvre plusieurs causes, et c'est ce tableau qui dit
laquelle a joué.

Un formulaire ouvert sans être connecté reste valable si l'on se connecte
ensuite dans un autre onglet : son jeton est celui de n'importe quel
visiteur. Cette tolérance ne vaut pas pour une correction ouverte par la
rédaction.
