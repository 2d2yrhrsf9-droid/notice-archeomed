# Présentation de l'extension

Cette extension pour WordPress sert à recueillir les **notices d'opération archéologique** d'une chronique annuelle : de courts comptes rendus de fouilles, de prospections ou de diagnostics, rédigés par les responsables d'opération et publiés chaque année dans un volume de revue. Elle remplace l'échange de fichiers Word par courriel par un formulaire en ligne, et livre à la rédaction des notices déjà mises aux normes de la revue, prêtes pour la chaîne d'édition.

## Pourquoi un formulaire

Une chronique reçoit plusieurs centaines de notices par an, accompagnées chacune d'une à trois illustrations. Reçues en pièces jointes, elles arrivent dans des formats, des mises en forme et des états très divers : titres construits chacun à sa façon, siècles écrits de cinq manières, images trop petites pour l'impression, légendes manquantes, mots-clés libres qu'aucun index ne relie. La rédaction passe alors l'essentiel de son temps à harmoniser, relancer et reformater.

Le formulaire inverse la démarche : il guide l'auteur champ par champ, lui signale au moment même ce qui posera problème, et produit de lui-même un document conforme.

## Ce que l'auteur remplit

- **La rubrique** du volume où paraîtra la notice, et jusqu'à deux renvois vers d'autres rubriques.
- **Les lieux** de l'opération, choisis dans le thésaurus géographique (commune, département ou région), le lieu-dit, l'année ou les années de l'opération.
- **La nature de l'opération** (fouille préventive, programmée, prospection, diagnostic…), parmi une liste fixe.
- **Les périodes et les mots-clés**, choisis dans le thésaurus Pactols, le vocabulaire de référence de l'archéologie, tenu par le réseau Frantiq. Chaque terme choisi garde son identifiant pérenne (ARK), ce qui permettra de bâtir les index du volume sans ressaisie. Un terme absent du thésaurus peut être proposé, mais l'auteur est prévenu qu'il ne sera pas retenu ; ces termes sont rassemblés à part, pour être proposés plus tard aux gestionnaires du thésaurus.
- **Les références administratives** : numéro d'autorisation, identifiant de l'opération dans la base nationale, lien vers le rapport final, organismes porteurs.
- **Les personnes** : responsable, co-responsable, co-auteur, avec leur institution.
- **Le texte**, dans un éditeur simple qui ne propose que ce que la revue imprime : italique, gras, exposant, petites capitales. Il n'y a pas de notes de bas de page, que la chronique n'admet pas.
- **Les illustrations**, une à trois, avec pour chacune un titre, une légende, des crédits, un **texte alternatif** obligatoire (ce qu'une personne qui ne voit pas l'image doit en savoir) et, pour un plan ou une carte, une description détaillée facultative. Une autorisation de reproduction peut y être jointe. L'ordre des figures se change en les faisant glisser.

## Ce que le formulaire vérifie

Le formulaire ne refuse que l'indispensable — un champ obligatoire vide, un fichier d'un format non admis ou trop lourd. Pour le reste, il **avertit sans bloquer** : une notice ne doit jamais être perdue parce qu'un contrôle s'est trompé. Il signale, par exemple :

- une image dont la définition est insuffisante pour la taille d'impression prévue ;
- une figure appelée dans le texte (« fig. 3 ») qui n'est pas jointe, ou une figure jointe que le texte n'appelle pas ;
- un paragraphe sans ponctuation finale, signe fréquent d'un texte coupé au copier-coller ;
- un ordinal fautif (« XIIème » pour « XIIe »), une année tapée avec une espace (« 1 250 ») ;
- un sigle qui n'est pas développé à sa première mention, ou qui n'est pas écrit à la forme de la revue (en capitales s'il s'épelle, avec une seule capitale s'il se prononce) ;
- un texte alternatif qui recopie la légende, qui se réduit à un nom de fichier, ou qui commence par « Image de » ;
- un texte trop court ou trop long au regard de la norme.

Un brouillon est gardé dans le navigateur : une page fermée par erreur ne fait pas tout perdre. Si le dépôt est refusé, le formulaire se rouvre rempli, et seuls les fichiers sont à redonner.

## Ce qui se passe après l'envoi

L'auteur reçoit aussitôt une **référence** de dépôt. Le formulaire rend la main en une fraction de seconde : la notice est enregistrée sur le site avant toute autre opération, puis expédiée en arrière-plan. Plusieurs centaines de personnes peuvent ainsi déposer le même jour sans que le site ralentisse, et deux dépôts simultanés ne se gênent jamais.

- **La rédaction** reçoit un courriel avec la notice lisible, le document Word mis aux normes, et les illustrations en version allégée ; les originaux restent sur le site, téléchargeables depuis l'administration.
- **L'auteur et les personnes citées** reçoivent une copie avec le même document Word, et un lien qui rouvre le formulaire rempli pendant un mois : s'ils repèrent une erreur, ils la corrigent et redéposent, et la rédaction est avertie que ce nouveau dépôt remplace l'ancien.
- Un courriel qui échoue n'efface rien : la notice reste en file, est représentée automatiquement, et l'administration signale ce qui n'est pas parti et pourquoi.

## Le document produit

Le document Word est construit sur la **feuille de styles Métopes**, la chaîne d'édition structurée utilisée pour publier en ligne et en volume imprimé à partir d'un même fichier. Chaque élément de la notice — titre, métadonnées, texte, légende, crédits — reçoit le style que la chaîne attend, ce qui supprime le stylage à la main.

Le document applique aussi les normes de la revue : typographie française (espaces insécables, apostrophes), siècles en petites capitales avec l'abréviation « s. », forme du titre, numérotation des figures. Ces normes ne sont pas figées dans le code : elles se règlent dans l'administration, avec pour chacune les choix possibles et un exemple, ce qui permet d'adapter l'outil à une autre revue.

Le texte alternatif de chaque image est inscrit dans le document à l'endroit où la chaîne d'édition le lit, pour qu'il accompagne l'image jusqu'à la publication en ligne.

## Ce que la rédaction trouve dans l'administration

- **La liste des notices reçues**, triable par rubrique, filtrable par état d'envoi, avec une recherche sur la référence et le nom du responsable.
- **La fiche de chaque notice** : son contenu, son historique d'envoi, ses illustrations, et un tableau de l'accessibilité des figures où la rédaction peut corriger un texte alternatif (l'ancienne version est conservée).
- **Par rubrique** : le fascicule, c'est-à-dire toutes les notices de la rubrique dans l'ordre du volume, en un seul document Word ; une page de relecture dans le navigateur ; et un **dossier complet pour la chaîne d'édition**, avec le document, les illustrations en haute et basse définition renommées selon la règle de la revue, et un fichier d'indexation.
- **Un récapitulatif quotidien** des dépôts, adressé aux personnes qui le souhaitent.
- **Les termes candidats** au thésaurus, avec un statut (à proposer, proposé, écarté) et un export en tableur.

## Les réglages

Tout ce qui change d'une revue à l'autre ou d'une année à l'autre se règle sans toucher au code : les destinataires, le numéro du volume en préparation et la règle de nommage des images, les normes éditoriales, la correspondance de chaque élément avec un style de la feuille Métopes, la feuille de styles elle-même, la protection du formulaire contre les robots, l'acheminement des courriels, et la provenance des mises à jour. Un onglet « Hébergement » dit ce que le serveur permet et ce qui lui manque.

## Accessibilité

Le formulaire est conçu pour être utilisable au clavier et avec un lecteur d'écran : chaque champ est étiqueté, les erreurs sont listées et reliées au champ concerné, aucune information ne repose sur la seule couleur, et la vérification anti-robot propose une alternative au geste de la souris. Un onglet de l'administration fait passer le formulaire publié à un outil de contrôle automatique libre et garde la trace de l'audit manuel de référence.

Au-delà du formulaire, l'extension cherche à rendre les **notices publiées** plus accessibles : textes alternatifs obligatoires, descriptions détaillées pour les figures complexes, sigles développés, siècles que la synthèse vocale lit comme des nombres.

## Ce que l'extension ne fait pas

- Elle ne publie rien : les notices restent privées, dans l'administration, jusqu'à leur traitement par la rédaction et la chaîne d'édition.
- Elle ne corrige pas le fond : les avis portent sur la forme et la complétude, la relecture scientifique reste celle de la rédaction.
- Elle ne remplace pas la chaîne d'édition : elle lui fournit des fichiers propres et structurés.

## Ses dépendances

Une installation WordPress ordinaire, avec PHP 7.2 ou plus récent. Pour fabriquer les versions allégées des images, l'extension Imagick de PHP. Pour le thésaurus, l'accès au service en ligne Pactols. Pour la vérification anti-robot, au choix une épreuve locale ou le service Turnstile de Cloudflare. Pour les courriels, le serveur de courriel du site ou un relais SMTP.
