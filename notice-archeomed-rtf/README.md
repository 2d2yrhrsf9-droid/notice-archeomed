# Formulaire des notices d’archéologie médiévale — version 3.15

Formulaire de soumission des notices d'opération pour la Chronique
d'*Archéologie médiévale*. La rédaction reçoit le courriel habituel,
accompagné d'un fichier **DOCX** portant la feuille de styles Métopes,
prêt à être relu puis versé dans la chaîne XML.

Si l'extension PHP ZipArchive est absente de l'hébergement, le plugin
produit automatiquement un RTF équivalent : les deux formats appliquent
exactement les mêmes styles.

Shortcode : `[notice_archeomed_pactols]`

## Installation

1. Extensions > Ajouter > Téléverser une extension
2. Choisir `notice-archeomed-rtf.zip`, installer, puis activer.

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

Le plugin refuse de s'activer si `modele-metopes.rtf` manque : sans lui, le
RTF ne peut pas être produit.

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

La question tirée au sort est scellée par une empreinte déposée dans le
formulaire, non retenue sur le serveur : une page mise en cache garde donc sa
question et reste valable. La réponse s'écrit en chiffres ou en toutes
lettres — refuser « sept » parce qu'on attendait « 7 » serait une brimade.

## Réglage indispensable avant la mise en service

Après activation, aller dans **Réglages > Notice Archéomed** et coller la
clé secrète Turnstile. Tant qu'elle est absente, le formulaire refuse toutes
les soumissions et un bandeau rouge le signale dans l'administration.

Un bouton « Tester la clé secrète » interroge Cloudflare et confirme que la
clé est reconnue, sans envoyer de notice.

La même page porte la liste des destinataires, **qui n'a pas de valeur par
défaut**. Tant que personne n'y reçoit les notices, les dépôts sont conservés
en file mais aucun ne part, et un bandeau rouge le signale sur toutes les
pages de l'administration. C'est donc le premier réglage à faire après
l'installation.

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
| `notice-archeomed-rtf.php` | Plugin principal : formulaire, contrôles, envoi |
| `class-notice-archeomed-docx.php` | Générateur du DOCX stylé |
| `modele-metopes.docx` | Gabarit Métopes au format DOCX (styles + mise en page) |
| `class-notice-archeomed-settings.php` | Page de réglages (Réglages > Notice Archéomed) |
| `class-notice-archeomed-file.php` | La file d'attente : inscription, expédition différée, reprises |
| `class-notice-archeomed-rtf.php` | Générateur du RTF stylé |
| `modele-metopes.rtf` | Feuille de styles Métopes (en-tête seul, sans contenu) |
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
remplacez `modele-metopes.rtf` par un export du nouveau modèle et rien
d'autre. Pour produire ce fichier, ouvrez un document appliquant le nouveau
gabarit et enregistrez-le au format RTF sous ce nom.

Un style qui aurait disparu du modèle ne provoque pas d'erreur : le
paragraphe concerné sort en Normal et se repère à la relecture.

## Correspondance champ / style

| Bloc de la notice | Style Métopes |
|---|---|
| Avis de correction (dépôt corrigé) | Normal |
| Rubrique principale (tête de notice) | `Title` |
| Sous-rubrique (famille d'opérations) | `TEI_Titre 1+rubrique` |
| Renvois vers d'autres rubriques | Normal |
| Commune (département), lieu-dit | `TEI_Titre 2+notice` |
| Nature de l'opération | `TEI_archeoCHR_fieldwork_method` |
| Année de l'opération | `TEI_archeoCHR_fieldwork_year` |
| Numéro d'autorisation | `TEI_archeoCHR_IDpatriarche` |
| Organisme(s) porteur(s) de l'opération | `TEI_archeoCHR_holder` |
| Texte de la notice | Normal |
| Titre d'une illustration | `TEI_figure_title` |
| Légende d'une illustration | `TEI_figure_caption` |
| Crédits d'une illustration | `TEI_figure_credits` |
| Indexation Pactols, contacts | Normal |

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

`notice-archeomed-<Commune>-<Année>-<aléa>.rtf`, par exemple
`notice-archeomed-Caen-2025-87f1ea09.rtf`. La commune et l'année en clair
facilitent le classement par rubrique avant montage du volume.

Le fichier est supprimé du serveur aussitôt après l'envoi.

## Texte saisi dans l'éditeur

Gras, italique, exposant et indice sont convertis. Les entités HTML sont
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
[TEI_figure_title]    Fig. 1 : Vue générale du chantier
[TEI_figure_caption]  Le mur de terrasse vu depuis le sud, en fin de fouille.
[TEI_figure_credits]  Cliché A. Dupont, université de Caen.
```

Le bloc est encadré — `TEI_figure_start` et `TEI_figure_end` portant
`— <figure> ———…` et `— </figure> ———…` — pour que la chaîne XML sache où la
figure commence et finit, et qu'un préparateur passant d'un outil à l'autre
retrouve la même chose sous les yeux.

Les lignes vides ne produisent rien : une illustration sans crédits n'a pas de
paragraphe de crédits.

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

Une notice qui n'a pas pu partir se represente d'elle-même, cinq fois, en
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
