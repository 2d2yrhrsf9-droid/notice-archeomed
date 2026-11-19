# Présentation de l'extension

Cette extension pour WordPress sert à recueillir les **notices d'opération archéologique** d'une chronique annuelle : de courts comptes rendus de fouilles, de prospections ou de diagnostics, rédigés par les responsables d'opération et publiés chaque année dans un volume de revue. Elle remplace l'échange de fichiers Word par courriel par un formulaire en ligne, et livre à la rédaction des notices déjà mises aux normes de la revue, prêtes pour la chaîne d'édition.

## Pourquoi un formulaire

Une chronique reçoit plusieurs centaines de notices par an, accompagnées chacune d'une à trois illustrations. Reçues en pièces jointes, elles arrivent dans des formats, des mises en forme et des états très divers : titres construits chacun à sa façon, siècles écrits de cinq manières, images trop petites pour l'impression, légendes manquantes, mots-clés libres qu'aucun index ne relie. La rédaction passe alors l'essentiel de son temps à harmoniser, relancer et reformater.

Le formulaire inverse la démarche : il guide l'auteur champ par champ, lui signale au moment même ce qui posera problème, et produit de lui-même un document conforme.

## Ce que l'auteur remplit

- **La rubrique** du volume où paraîtra la notice (et, pour la rubrique V, sa sous-rubrique), jusqu'à deux renvois vers d'autres rubriques, puis **la nature de l'opération** (fouille préventive, programmée, prospection, diagnostic…), parmi une liste fixe.
- **Les lieux** de l'opération, choisis dans le thésaurus géographique (commune, département ou région), le lieu-dit, l'année ou les années de l'opération. Le titre de la notice se compose à mesure sous les yeux de l'auteur, la commune en capitales comme dans la revue.
- **Les périodes et les sujets**, une période et un sujet au moins, choisis dans le thésaurus Pactols, le vocabulaire de référence de l'archéologie, tenu par le réseau Frantiq. Chaque terme choisi garde son identifiant pérenne (ARK), ce qui permettra de bâtir les index du volume sans ressaisie. Un terme absent du thésaurus peut être gardé, pour qu'une panne du service n'empêche aucun dépôt, mais l'auteur est prévenu qu'il ne sera pas retenu ; ces termes sont rassemblés à part, pour être proposés plus tard aux gestionnaires du thésaurus.
- **Les références administratives** : numéro d'autorisation, identifiant de l'opération dans la base nationale, lien vers le rapport final, organismes porteurs.
- **Les personnes** : responsable, co-responsable, co-auteur, avec leur institution ; et, quand quelqu'un dépose pour le responsable, son nom, son prénom et son adresse.
- **Le texte**, dans un éditeur simple qui ne propose que ce que la revue imprime : un niveau d'intertitre, italique, gras, exposant, petites capitales. Il n'y a pas de notes de bas de page, que la chronique n'admet pas.
- **Les illustrations**, une à trois, quarante méga-octets au plus pour l'ensemble. Chacune a sa carte : pour la publication, un titre et des crédits obligatoires, une légende et une autorisation de reproduction facultatives ; puis, encadré à part, un **texte pour un public empêché de voir** l'image, obligatoire — le texte alternatif, que les lecteurs d'écran lisent à la place de l'image. L'auteur y choisit la **nature de la figure** (photographie, plan, carte, graphique, planche, tableau en image, autre), ce qui adapte le conseil donné et, pour un graphique ou un tableau en image, fait demander les données en texte. Pour un plan ou une carte, il peut poursuivre par une description détaillée, publiée sous la légende. L'ordre des figures se change en les faisant glisser.

Chaque carte montre une vignette de l'image, TIFF et PDF compris, qu'un clic agrandit à la taille de l'écran : la légende et le texte alternatif s'écrivent mieux devant l'image entière. La carte dit aussi à quelle taille la figure pourra s'imprimer (« Imprimable jusqu'à 25 × 17 cm environ, à 300 ppp »), mesure plus parlante qu'un nombre de pixels. Un TIFF trop lourd se passe en JPEG de haute qualité d'un bouton, dans le navigateur, sans logiciel à ouvrir. Une figure préparée pour l'imprimeur, en CMJN avec son profil de couleur, garde ses couleurs dans les aperçus et les JPEG tirés pour la revue : le profil est lu, comme Photoshop le lit. Un fichier qui ne passe toujours pas devient une **figure envoyée à part** : l'auteur le dépose sur un service de transfert, colle le lien et la date d'expiration dans la carte, et remplit les mêmes textes que pour une autre figure.

## Ce que le formulaire vérifie

Le formulaire ne refuse que l'indispensable — un champ obligatoire vide, un fichier d'un format non admis ou trop lourd. Pour le reste, il **avertit sans bloquer**, sauf sur les figures, où il arrête l'envoi tant que les appels de figure sont dans le désordre ou qu'un texte alternatif ne fait que reprendre le titre. Ces points-là se lisent à part, sous « À vérifier avant l'envoi », et une case permet de passer outre quand le renvoi est juste : une notice ne doit jamais être perdue parce qu'un contrôle s'est trompé. Il signale, par exemple :

- une image dont la définition est insuffisante pour la taille d'impression prévue ;
- une figure appelée dans le texte (« fig. 3 ») qui n'est pas jointe, une figure jointe que le texte n'appelle pas, ou une figure 2 appelée avant la 1 ;
- un paragraphe sans ponctuation finale, signe fréquent d'un texte coupé au copier-coller ; une ligne courte sans ponctuation, qui est sans doute un intertitre à poser avec le bouton T1 (plusieurs lignes courtes qui se suivent ne font qu'un avis) ; ou au contraire un intertitre qui finit par un point ;
- un ordinal fautif (« XIIème » pour « XIIe »), une année tapée avec une espace (« 1 250 ») ;
- un sigle qui n'est pas développé à sa première mention, ou qui n'est pas écrit à la forme de la revue ;
- un texte alternatif qui recopie la légende, qui se réduit à un nom de fichier, ou qui commence par « Image de » ;
- un texte trop court ou trop long au regard de la norme.

Les avis sur le texte suivent la frappe : ils se mettent à jour une seconde après la dernière touche. Le paragraphe en cours d'écriture n'est pas jugé sur sa ponctuation tant qu'on y écrit.

Un brouillon est gardé dans le navigateur : une page fermée par erreur ne fait pas tout perdre. Si le dépôt est refusé, le formulaire se rouvre rempli, et seuls les fichiers sont à redonner.

## Ce qui se passe après l'envoi

L'auteur reçoit aussitôt une **référence** de dépôt. Le formulaire rend la main en une fraction de seconde : la notice est enregistrée sur le site avant toute autre opération, puis expédiée en arrière-plan. Plusieurs centaines de personnes peuvent ainsi déposer le même jour sans que le site ralentisse, et deux dépôts simultanés ne se gênent jamais.

- **La rédaction** reçoit un courriel avec la notice lisible, chaque figure en aperçu au-dessus de sa légende, le document Word mis aux normes et les autorisations de reproduction. Les originaux ne voyagent pas : ils restent sur la fiche de la notice ou sont rangés sur sDrive. Son titre met la commune en capitales, comme la revue. Le courriel dit qui a déposé, donne le lien d'une figure envoyée à part et mène d'un clic à la correction de la notice.
- **L'auteur et les personnes citées** reçoivent une copie avec le même document Word, et un lien qui rouvre le formulaire rempli pendant un mois : s'ils repèrent une erreur, ils la corrigent, et la rédaction reçoit la notice de nouveau, marquée comme corrigée, sous la même référence.
- **Sur sDrive**, quand le site y est relié, les originaux, les autorisations et le Word de chaque notice se rangent après l'envoi, dans un dossier par rubrique et par commune. Le site n'en garde que les aperçus.
- Un courriel qui échoue n'efface rien : la notice reste en file, est représentée automatiquement, et l'administration signale ce qui n'est pas parti et pourquoi.

## Le document produit

Le document Word est construit sur la **feuille de styles Métopes**, la chaîne d'édition structurée utilisée pour publier en ligne et en volume imprimé à partir d'un même fichier. Chaque élément de la notice — titre, métadonnées, texte, légende, crédits — reçoit le style que la chaîne attend, ce qui supprime le stylage à la main.

Le document applique aussi les normes de la revue : typographie française (espaces insécables, apostrophes), siècles en petites capitales avec l'abréviation « s. », forme du titre, numérotation des figures. Ces normes ne sont pas figées dans le code : elles se règlent dans l'administration, avec pour chacune les choix possibles et un exemple, ce qui permet d'adapter l'outil à une autre revue. Seules la liste des rubriques et des sous-rubriques, et celle des champs obligatoires, sont dans le code.

Le texte alternatif de chaque image est inscrit dans le document à l'endroit où la chaîne d'édition le lit ; son passage jusqu'à la publication en ligne dépend de cette chaîne.

## Ce que la rédaction trouve dans l'administration

- **La liste des notices reçues**, triable par rubrique, filtrable par état d'envoi, avec une recherche sur la référence et le nom du responsable, et pour chaque notice un lien qui écrit d'un coup à toutes ses adresses.
- **La fiche de chaque notice** : son contenu, son historique d'envoi, les adresses auxquelles les copies sont parties, ses illustrations, et un tableau de l'accessibilité des figures où la rédaction peut corriger un texte alternatif (l'ancienne version est conservée).
- **Par rubrique** : le fascicule, c'est-à-dire toutes les notices de la rubrique dans l'ordre du volume, en un seul document Word ; une page de relecture dans le navigateur ; et un **dossier complet pour la chaîne d'édition**, avec le document, les illustrations en haute et basse définition renommées selon la règle de la revue, et un fichier d'indexation. Quand le site est relié à sDrive, ce dossier s'y écrit directement, à côté des originaux.
- **La relecture sur le site**, une fois la campagne close, sur une page où les coordinateurs se connectent sans entrer dans l'administration : chaque coordinateur relit les notices de sa rubrique dans l'ordre du volume. La direction de la revue les relit toutes, dans un sommaire du volume où chaque rubrique se déplie, et **valide** chaque notice : la version validée est la version définitive, qui ne se corrige plus sauf réouverture, notée au journal avec son motif. Un rôle « Direction de la revue » lui donne cette page et l'extension entière — notices reçues et réglages —, sans le reste de l'administration. Une notice ouverte est verrouillée pour les autres ; chaque enregistrement garde la version précédente et produit un Word à suivi de modifications ; un journal dit qui a fait quoi. Les lieux du titre se cherchent dans Pactols, comme au dépôt. Une modification de fond passe par l'accord de l'auteur, que la direction demande d'un bouton. La direction peut aussi voir la page comme la verra un coordinateur, ou la coordination d'une rubrique, même avant la clôture : rien de ce qu'elle y fait ne s'enregistre.
- **Un récapitulatif quotidien** des dépôts, chaque matin, qui range les notices du jour dans l'ordre du volume et dit l'état de chacune.
- **Les termes candidats** au thésaurus, avec un statut (à proposer, proposé, écarté) et un export en tableur.
- **L'annuaire des auteurs** : une ligne par personne, classée par nom, à emporter pour la liste de diffusion qui ouvre chaque campagne ou en tableur. Une personne qui ne veut plus d'envois s'en retire d'un bouton, sans que ses notices changent ; une adresse retirée peut ensuite être supprimée définitivement.

## Les réglages

Tout ce qui change d'une revue à l'autre ou d'une année à l'autre se règle sans toucher au code : les destinataires des notices et du récapitulatif, le numéro du volume en préparation et la règle de nommage des images, les normes éditoriales, la correspondance de chaque élément avec un style de la feuille Métopes, la feuille de styles elle-même, la protection du formulaire contre les robots, l'acheminement des courriels, et la provenance des mises à jour.

L'onglet « Hébergement » dit ce que le serveur permet et ce qui lui manque, relie le site à sDrive et donne la cause exacte des derniers envois refusés à des auteurs. L'onglet « Stockage » dit ce que prennent les notices sur le disque. Le site se garde lui-même : si le disque approche de son plafond, ou si une saisie abusive envoie trop de fichiers en une heure, la notice est reçue sans ses fichiers, que l'auteur envoie alors à part, plutôt que d'être refusée.

## Accessibilité

Le formulaire a été conçu en tenant compte du clavier et des lecteurs d'écran : chaque champ est étiqueté, les erreurs sont listées et reliées au champ concerné, la vérification anti-robot se manœuvre au clavier, et une adresse de courriel est donnée en tête de page pour qui préfère envoyer sa notice autrement. Il n'a pas encore fait l'objet d'un audit RGAA complet ni d'essais systématiques avec des utilisateurs de lecteurs d'écran. Un onglet de l'administration fait passer le formulaire publié à un outil de contrôle automatique libre et garde la trace de l'audit manuel de référence.

Au-delà du formulaire, l'extension cherche à rendre les **notices publiées** plus accessibles : textes alternatifs obligatoires, données recopiées en texte pour les graphiques et les tableaux en image, sigles développés, siècles que la synthèse vocale lit comme des nombres.

## Ce que l'extension ne fait pas

- Elle ne publie rien : les notices restent privées jusqu'à leur traitement par la rédaction et la chaîne d'édition. La page de relecture est une page du site, mais elle ne s'ouvre qu'après connexion, et chacun n'y voit que les notices de sa rubrique.
- Elle ne corrige pas le fond : les avis portent sur la forme et la complétude, la relecture scientifique reste celle de la direction et des coordinateurs.
- Elle ne remplace pas la chaîne d'édition : elle lui fournit des fichiers propres et structurés.

## Ses dépendances

Une installation WordPress ordinaire, avec PHP 7.2 ou plus récent. Pour les images, l'extension Imagick de PHP, quand l'hébergement l'offre ; à son défaut, le navigateur de l'auteur convertit lui-même les TIFF et les PDF, et le serveur réduit les JPEG et les PNG. Pour le thésaurus, l'accès au service en ligne Pactols. Pour la vérification anti-robot, au choix une épreuve locale ou le service Turnstile de Cloudflare. Pour les courriels, le serveur de courriel du site ou un relais SMTP. Pour ranger les originaux hors du site, un compte sDrive, le stockage en ligne de l'établissement ; sans lui, tout reste sur le serveur.
