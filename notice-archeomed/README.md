# Formulaire des notices d’archéologie médiévale — version 3.47

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
Le fond et la pièce sont dessinés à chaque affichage : un lecteur d'écran ne
peut pas résoudre un alignement visuel, et c'est la limite reconnue de ce
genre de vérification. Turnstile, lui, sait s'en passer.

**Le sceau porte l'heure.** Il ne portait que la réponse : un couple
(réponse, sceau) récolté une fois valait indéfiniment, sur toutes les pages.
Il vaut désormais l'heure en cours et la précédente — deux heures au pire, de
quoi laisser partir un formulaire ouvert à la charnière. Rien n'est retenu
entre l'affichage et l'envoi, et une page mise en cache reste donc valable.

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
aussi y coller sa clé secrète ; un bouton « Tester la clé » la vérifie sans
envoyer de notice. La pièce de puzzle, choisie par défaut, ne demande rien.

Les réglages sont rangés en onglets : Destinataires, Numéro et iconographie,
Formulaire, Courriel (avec le rythme d'envoi), Feuille de styles, Mises à
jour, Hébergement. Chacun s'enregistre pour son compte.

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
reçoivent : les pièces jointes pèsent jusqu'à vingt méga-octets, et les
répéter par destinataire ferait payer la liste au poids. Les destinataires se
voient donc les uns les autres, ce qui est le cas d'une rédaction.

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
| `modele-metopes.docx` | **Feuille de styles Métopes de référence** |
| `modele-metopes.rtf` | La même feuille dans l'autre format, pour le repli |
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
[TEI_archeoCHR_fieldwork_method]              Nature de l'opération : archéologie du bâti
[TEI_archeoCHR_keywords_subjects:chronology]  Période historique : Temps modernes, Époque contemporaine
[TEI_archeoCHR_fieldwork_year]                Année de l'opération : 2024
[TEI_archeoCHR_IDpatriarche]                  Numéro d'autorisation : 2269
[TEI_archeoCHR_holder]                        Organisme porteur de l'opération : …
[TEI_archeoCHR_keywords_subjects]             Mots-clés : couvent, chartreux, cloître
```

Un **point** sépare la commune du lieu-dit, qui s'écrit en italique. Les
natures gardent la casse de Pactols — « archéologie du bâti » —, les périodes
prennent une capitale initiale — « Bas Moyen Âge ». Le bloc de responsabilité
reste collé au point final du texte : « (Responsable de l'opération : …) ».

## Le nom des illustrations

Pas d'accent, pas d'espace, le souligné pour seul séparateur. C'est la règle
de la chaîne éditoriale de la revue, suivie à la lettre : un dossier passé
d'un outil à l'autre doit garder ses noms.

« Briollay_49_ Palaisjustice.EPS » devient « Briollay_49_Palaisjustice.eps ».
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
| Organisme(s) porteur(s) de l'opération | `TEI_archeoCHR_holder` |
| Mots-clés | `TEI_archeoCHR_keywords_subjects` |
| Texte de la notice | Normal |
| Image d'une illustration | Normal |
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

Le lieu-dit passe en italique dans le titre de notice. Le bloc
« Responsable d'opération : … » se colle à la fin du dernier paragraphe du
texte, entre parenthèses, comme dans le fichier de stylage de référence.

Les ARK Pactols sont posés en liens cliquables, dont le libellé se limite à
l'identifiant (`26678/pcrtd1Ms3ERUXz`).

## Nom du fichier joint

`notice-archeomed-<Commune>-<Année>-<aléa>.docx` (`.rtf` sur un hébergement
sans `ZipArchive`), par exemple `notice-archeomed-Caen-2025-87f1ea09.docx`.
La commune et l'année en clair facilitent le classement par rubrique avant
montage du volume. Ses propriétés portent le titre de la notice ; la page est
en A4 ; chaque image a pour texte de remplacement le titre de sa figure.

## Texte saisi dans l'éditeur

Gras, italique, exposant et indice sont convertis. Une liste, un intertitre
ou une citation collés depuis Word deviennent chacun un paragraphe, au lieu de
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
puis du complément en italique. La parenthèse se remplit toute seule à partir
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

## La hiérarchie du document

Un fascicule, et une notice isolée, se composent ainsi :

```
[Title]                            IV. – Sépultures et nécropoles
[TEI_Titre 1+rubrique]             IV. 1. – Opérations de terrain
[TEI_Titre 2+notice]               Beneuvre, Vanvey (Côte-d'Or), Mont Aigu
[TEI_archeoCHR_fieldwork_method]   Fouille programmée [26678/crtSrWQs2w2KV]
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

Chaque fichier déposé reçoit sa propre ligne dans le formulaire : **titre**,
**légende**, **crédits**. Un champ libre commun recevait tout auparavant, et
la rédaction devait deviner quelle ligne allait avec quelle image.

Dans le document Word, chaque illustration donne un bloc de figure aux normes
Métopes — trois styles distincts que la chaîne XML sait séparer, là où tout
partait dans le seul titre de figure :

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
(FileSender de RENATER, espace de partage de son établissement), qui arrive
dans le courriel et le document.

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

**Surveillez la place.** Trois fichiers de vingt méga-octets par notice, sur
une campagne de plusieurs milliers de sollicitations, cela se compte en
dizaines de giga-octets. La fiche indique le poids conservé, et supprimer une
notice efface ses illustrations avec elle.

Le document Word, lui, ne se garde pas : il se refabrique à partir de la
saisie. L'archiver reviendrait à figer une mise en forme que la feuille de
styles fera évoluer.

## Ce qui se passe quand ça ne passe pas

**La saisie n'est jamais perdue.** Une notice se rédige en une heure : la
perdre parce qu'on a oublié la vérification anti-robot, ou parce que la
page avait expiré pendant qu'on écrivait, est le défaut le plus coûteux qu'un
formulaire puisse avoir. Tout refus met donc la saisie de côté et le
formulaire se remplit de lui-même au retour — champs, cases cochées, mots-clés
Pactols, texte de la notice.

Les fichiers font exception : aucun navigateur ne permet de regarnir un champ
de fichier, il faut les redéposer. Ce qu'on avait écrit à leur sujet — titre,
légende, crédits — revient dès qu'ils sont rechoisis, dans le même ordre.

La reprise vit une heure après un refus.

## Corriger un dépôt déjà envoyé

C'est en recevant sa copie qu'on voit ce qu'on n'a pas vu en saisissant. La
copie porte donc un lien qui rouvre le formulaire rempli de la même saisie ;
il vit un mois. Les illustrations sont à redéposer — même raison qu'au-dessus.

Le dépôt corrigé est une notice neuve : rien ne la distinguerait de la
première, et la rédaction garderait les deux. Trois marques l'en empêchent :

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
| Envois | 150 par adresse IP | seulement ce qui est réellement parti |
| Envois | 25 par adresse électronique | idem, mais là une personne est vraiment désignée |

**Rien n'est effacé tant que rien n'est parti.** Le fichier stylé et les
illustrations vivent dans le répertoire temporaire du serveur sous un nom
portant `notice-archeomed-garde-`, à l'abri de la purge quotidienne, et ne
sont supprimés qu'une fois le courriel de la rédaction remis.

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
2. **famille d'opération** — opérations de terrain, prospections, projets
   collectifs de recherche,
3. **commune**, par ordre alphabétique.

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

Une notice qui n'a pas pu partir se retente d'elle-même, cinq fois, en
espaçant les essais. Si la file s'allonge sans redescendre, c'est que le
planificateur de WordPress ne tourne pas sur cet hébergement : un bandeau le
signale dans l'administration, avec un bouton **Expédier maintenant**, et
l'on peut alors repasser en envoi immédiat depuis la page de réglages.

### Ce qui n'est plus compté par adresse IP

Une adresse IP ne désigne pas une personne : l'Inrap, une DRAC, une
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
| Les avis donnés au dépôt | tous | chacun se coche ou se décoche |

Les documents produits après un changement suivent les nouvelles normes —
un fascicule ou un dossier refabriqué aussi. L'année d'une opération sur
plusieurs années, elle, s'écrit au dépôt.

La correspondance des champs avec les styles Métopes n'y est pas : ce n'est
pas une norme de revue, mais le contrat avec la chaîne XML.

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

Un corpus de cas, dans les essais, fixe ce que chaque règle doit rendre.

## Les contrôles du dépôt

Au dépôt, la notice est relue par des règles qui **avertissent sans jamais
refuser** : un texte qui s'arrête sans ponctuation (collage tronqué), un
paragraphe coupé en deux, une figure appelée dans le texte qui n'est pas
jointe ou une figure jamais appelée, une photographie sous la norme annoncée
(10 × 15 cm à 300 ppp, soit 1 182 × 1 772 pixels, lus dans l'en-tête du
fichier), une figure sans crédits, « XIIème » pour « XIIe », une année tapée
« 1 250 », un nom en capitales, deux personnes
dans un même champ, une adresse dans le champ de l'institution.

Ces avis s'affichent à l'auteur dans le formulaire, à la sortie du champ, et
partent sous « À vérifier » dans le courriel, dans le document (style « à
supprimer ») et dans la page de relecture du dossier. La norme des
photographies est posée en un seul endroit, qui sert à l'aide et au contrôle.

Le lieu-dit perd le point final qu'on y met par habitude, sauf abréviation ;
une opération sur plusieurs années s'écrit « 2004-2005 ».

## Le thésaurus Pactols

Les recherches sont mises en réserve une journée : le même terme cherché deux
fois ne repart pas sur le réseau. Au-delà de deux cents interrogations par
heure et par connexion, le relais cesse de répondre — assez large pour qu'on
ne le voie jamais en remplissant un formulaire, assez étroit pour que le site
ne serve pas à marteler frantiq.fr.

## Journalisation

Les incidents (échec de génération, refus d'envoi, erreur de téléversement)
sont écrits dans le journal PHP avec le préfixe `Notice Archeomed:`. En cas
de comportement inattendu, c'est le premier endroit à consulter.
