# Notice technique

Ce document s'adresse à qui doit reprendre l'extension : la corriger, la faire évoluer, l'adapter à une autre revue. Il décrit son architecture, ses données, ses tâches planifiées, ses réglages, la fabrication des documents, ses essais et sa publication. Le fonctionnement vu de l'utilisateur est décrit dans la présentation, l'autre document de cet onglet ; le `README.md` du dossier détaille les choix éditoriaux un à un.

Le code est commenté en français, en phrases : chaque fonction dit ce qu'elle fait et, le plus souvent, pourquoi elle a pris sa forme actuelle. Ces commentaires sont la première documentation ; ce qui suit en est la carte.

## 1. En bref

| | |
|---|---|
| Nature | Extension WordPress, sans dépendance installée par Composer ni par npm |
| PHP | 7.2 au moins (essayé en 7.2, 8.1, 8.3 et 8.4) |
| WordPress | 5.6 au moins (essayé en 6.9 et en dernière version) |
| Extensions PHP | `zip` (DOCX et dossiers ; sans elle, repli en RTF), `imagick` (aperçus et conversions ; sans elle, le navigateur convertit TIFF et PDF, et GD réduit JPEG et PNG), `mbstring` conseillée |
| Bibliothèques chargées par le navigateur | Quill 2.0.3 (éditeur du texte, `assets/quill/`), UTIF.js (TIFF, `assets/utif/`), Little CMS 2 (lcms-wasm 1.0.5, profils de couleur, `assets/lcms/`) et pdf.js (PDF, `assets/pdfjs/`), chargés à la demande, tous servis par le site lui-même ; Turnstile de Cloudflare (selon la protection choisie), axe-core 4.10.2 (onglet Accessibilité seulement, cdnjs, avec empreinte SRI) |
| Services joints par le serveur | Pactols (lecture des termes, coupable dans les réglages), Cloudflare (vérification Turnstile, si choisie), sDrive (WebDAV, si le site y est relié), GitHub (mises à jour), le serveur de courriel |
| Shortcodes | `[notice_archeomed_pactols]` (le dépôt), `[relecture_archeomed]` (la relecture) |
| Domaine de traduction | `notice-archeomed` (les textes sont écrits en français dans le code) |

Le principe directeur : **accepter vite, expédier ensuite**. Le dépôt d'un auteur est inscrit en base en une fraction de seconde ; tout ce qui est lent — courriels, accusés de réception, lecture du thésaurus, rangement et export sur sDrive — se fait après, sous le planificateur de WordPress. Les versions allégées des figures et le document Word se fabriquent encore dans la requête du dépôt, mais dans un bloc qui attrape toute erreur : leur échec ne fait ni échouer ni doubler un dépôt déjà inscrit. Deux dépôts simultanés ne doivent jamais se gêner, et une notice ne doit jamais se perdre parce qu'un courriel échoue ou qu'une métadonnée manque.

## 2. Les fichiers

```
notice-archeomed/                     le dossier de l'extension, tel qu'il est installé
  notice-archeomed.php                classe principale Notice_Archeomed_Pactols (≈ 16 200 lignes)
  class-notice-archeomed-file.php     la file d'attente et l'administration des notices reçues
  class-notice-archeomed-settings.php la page de réglages et ses onglets
  class-notice-archeomed-docx.php     le générateur de DOCX à partir du gabarit Métopes
  class-notice-archeomed-rtf.php      le générateur RTF, repli sans ZipArchive
  class-notice-archeomed-paquet.php   le dossier Métopes d'une rubrique (zip) et les images
  class-notice-archeomed-thesaurus.php la lecture des concepts Pactols et les blocs d'index TEI
  class-notice-archeomed-typographie.php la typographie française du texte
  class-notice-archeomed-lieu-dit.php la mise en forme du lieu-dit et son éditeur d'une ligne
  class-notice-archeomed-forme.php    la mise en forme des titres, légendes et descriptions des figures
  class-notice-archeomed-controles.php les avis donnés au dépôt, communs au serveur et au navigateur
  class-notice-archeomed-normes.php   les normes éditoriales, en réglages
  class-notice-archeomed-styles.php   la correspondance des blocs avec les styles Métopes, en réglages
  class-notice-archeomed-sdrive.php   l'accès WebDAV à sDrive et la liaison du site
  class-notice-archeomed-rangement.php le rangement des fichiers des notices sur sDrive
  class-notice-archeomed-export.php   l'écriture du dossier Métopes sur sDrive
  class-notice-archeomed-accessibilite.php l'onglet Accessibilité et l'encart des figures sur la fiche
  class-notice-archeomed-candidats.php l'onglet Candidats à Pactols
  class-notice-archeomed-documentation.php l'onglet Documentation, qui affiche ces pages
  class-notice-archeomed-confidentialite.php l'export et l'effacement des données personnelles
  class-notice-archeomed-relecture.php la relecture sur le site : droits, verrou, états, journal, versions
  class-notice-archeomed-stockage.php l'onglet Stockage
  class-notice-archeomed-annuaire.php l'onglet Annuaire et ses deux exports
  class-notice-archeomed-nommage.php  la règle des noms de fichiers
  class-notice-archeomed-maj.php      les mises à jour depuis GitHub
  uninstall.php                       ce que la suppression de l'extension efface
  index.php                           page vide, contre le listage du dossier
  modele-metopes.docx                 le gabarit Métopes livré (feuille de styles de référence)
  modele-metopes.rtf                  son pendant RTF
  assets/                             icônes, Quill, UTIF.js, Little CMS, pdf.js, profil sRGB
  doc/                                la présentation et cette notice
  README.md                           les choix éditoriaux et fonctionnels, détaillés
.github/workflows/publier.yml         intégration continue et publication des versions
.github/essais/                       les essais de non-régression, et un faux sDrive
verifier-la-syntaxe                   filet de syntaxe PHP sans PHP (Python)
verifier-les-styles                   confronte les styles du code au stylage de référence
empaqueter                            fabrique l'archive à téléverser
```

Le dépôt Git contient le dossier de l'extension et les outils qui l'entourent ; seul `notice-archeomed/` part dans l'archive installée.

L'ordre des `require_once`, en tête de `notice-archeomed.php`, compte : les classes utilitaires (nommage, paquet, mises à jour, RTF, typographie, contrôles, normes, styles, DOCX) et celles de sDrive (accès, rangement, export) avant les réglages et la file, qui les emploient ; puis le thésaurus, l'accessibilité, les candidats, la documentation, la confidentialité, la relecture, le stockage et l'annuaire. Viennent ensuite les instanciations : `Notice_Archeomed_Relecture`, `Notice_Archeomed_Settings`, `Notice_Archeomed_Accessibilite`, `Notice_Archeomed_Candidats`, `Notice_Archeomed_Annuaire`, `Notice_Archeomed_Documentation`, `Notice_Archeomed_Confidentialite`, puis la file en **globale** `$notice_archeomed_file` (indispensable sous WP-CLI, qui charge WordPress dans une fonction : sans `global`, une seconde file naissait et doublait les crochets), puis le module de mises à jour (`$notice_archeomed_maj`), et, en fin de fichier, la classe principale en globale `$notice_archeomed_plugin`.

## 3. Les classes et leurs responsabilités

### Notice_Archeomed_Pactols (notice-archeomed.php)

Le nom est historique : la classe porte tout le formulaire, pas seulement Pactols. Ses grands blocs, dans l'ordre du fichier :

- **Constantes** : gabarits, plafonds (`MAX_FILES` = 3, `MAX_TOTAL_FILESIZE` = 40 Mo — le plafond réel est le plus petit de celui-ci et de ceux de PHP, voir `plafonds_des_fichiers()`), quotas (`MAX_ATTEMPTS_PER_HOUR`, `MAX_SENDS_PER_IP`, `MAX_SENDS_PER_EMAIL`, `MAX_LOOKUPS_PER_HOUR`, `MAX_SENDS_GLOBAL_PER_HOUR`, `MAX_RESERVES_PAR_IP`, `MAX_POIDS_PAR_HEURE`), extensions admises, constantes du puzzle, identifiants des thésaurus Pactols (`TH_1` pour sujets et périodes, `th17` pour les lieux), rubriques et sous-rubriques.
- **Listes métier** : `$rubriques` (sept rubriques), les natures d'opération avec leur ARK (`$natures`), les familles d'opérations, les cadres et les types d'opération (`CADRES`, `TYPES_D_OPERATION`, depuis la 3.107 : deux cases de cadre, Préventive et Programmée, et cinq types, que `composer_les_natures()` recompose en termes Pactols et `decomposer_les_natures()` défait ; `NATURES_DU_CADRE` pour un cadre sans type, `NATURES_RENOMMEES` pour « Archéologie du bâti » et « Opération de diagnostic »), les sous-rubriques de la rubrique V, les anciens libellés de rubrique (`RUBRIQUES_ANCIENNES`, pour relire les notices déposées avant un changement de nom).
- **Protection** : `protection()`, `verifier_la_protection()`, le puzzle (`defi_du_puzzle()`, `verifier_le_puzzle()`, sceau HMAC lié à l'heure d'affichage `na_ts`, valable 24 h, retenu après un dépôt réussi pour ne servir qu'une fois ; Cloudflare injoignable, le transient `na_turnstile_injoignable` ajoute le puzzle à Turnstile), Turnstile (`verify_turnstile()`), et `protection_suspendue()` pour les sites d'essai.
- **Formulaire** : `render_form()` (de la ligne ≈ 2 940 à ≈ 8 600 : HTML, CSS et JavaScript en ligne, dont la conversion des TIFF et des PDF), `enqueue_assets()`, `reglages_de_conversion()` (ce que le navigateur doit savoir pour convertir), la reprise d'une saisie (`garder_la_saisie()`, `reprise()`, `repris*()`), les messages de retour (`messages_du_retour()`, `panneau_de_fin()`).
- **Réception** : `handle_submission()` et ses `collect_*()`, `handle_uploads()`, `recevoir_les_autorisations()`, `champs_manquants()`, `entrees_attendues()` (ramène chaque champ à la forme attendue avant toute lecture), `refuser_l_envoi_trop_lourd()`, `recevoir_les_conversions()` (les JPEG tirés par le navigateur), `garder_le_disque()`, `noter_le_refus()`.
- **Fabrication** : `build_notice()` (le HTML du courriel, titre en tête avec la commune en capitales), `remplir_le_document()` (le DOCX ou RTF d'une notice), `fabriquer_le_fascicule()`, `page_de_relecture()`, `indexation_de()` (blocs d'index TEI), la typographie et les siècles en HTML.
- **Expédition** : `expedier_de_la_file()` (appelé par le planificateur), `expedier()`, `pieces_du_courriel()`, `figures_dans_le_corps()` (les aperçus posés dans le corps du message), SMTP (`acheminer_par_smtp()`), les essais d'envoi et le diagnostic.
- **Thésaurus** : la file de résolution des termes (`resoudre_en_tache()`, `resoudre_les_termes()`, `mettre_en_attente()`…), les formes préférées.
- **Fichiers** : rangement des illustrations, des conversions et des autorisations (`archiver_les_conversions()`, `conversions_rangees()`), versions allégées (aperçus), purge des fichiers temporaires, téléchargements protégés, branchement des tâches de sDrive (`brancher_la_file()`).
- **Téléchargements d'administration** : document d'une notice, fascicule, dossier Métopes, illustration.

### Notice_Archeomed_File (class-notice-archeomed-file.php)

Le type de contenu `na_notice`, la file d'attente et tout l'écran « Notices reçues » : colonnes, tris, vues par état, recherche étendue, actions de ligne et groupées, fiche d'une notice (en-tête, notice, suivi, dépannage, illustrations), bandeaux d'alerte (adresse manquante, échecs, retards), récapitulatif quotidien, liens des fascicules et dossiers par rubrique, renvois entre rubriques, classement du volume, mise à niveau des notices anciennes (`rattraper_les_anciennes()`). La liste donne, pour chaque notice, un lien « Écrire aux N adresses de la notice » ; la fiche montre la trace des copies envoyées aux auteurs (`_na_copies`).

### Les autres classes

- **Notice_Archeomed_Settings** : la page de réglages (sous-menu du type `na_notice`), ses onglets (`onglets()`), l'option `notice_archeomed_options` et son nettoyage (`sanitize()`), les essais de la page (`faire_un_essai()` : Cloudflare joignable et clé Turnstile reconnue (`test_cloudflare()`), essai de charge (`essai_de_charge()`, `cle_d_essai_valide()`, `ecarter_les_notices_d_essai()` ; marque `_na_essai`), envoi, essais successifs, poids, GitHub, Pactols), l'état de l'hébergement (`etat_du_serveur()`), les derniers envois refusés (`tableau_des_refus()`), la mesure du disque face au plafond (`PLAFOND_DISQUE`). `get()` lit une valeur en donnant la priorité à une constante de `wp-config.php`.
- **Notice_Archeomed_DOCX** : ouvre le gabarit, indexe ses styles (nom affiché ⇒ identifiant), construit `word/document.xml` paragraphe par paragraphe et recopie tout le reste du gabarit. Voir la section 8.
- **Notice_Archeomed_RTF** : même interface, pour un hébergement sans ZipArchive. Le DOCX reste la voie normale.
- **Notice_Archeomed_Paquet** : l'atelier d'un dossier Métopes (`style/`, `XML/`, `icono/hr`, `icono/br`, `icono/originaux`), les deux définitions de chaque image, le lisez-moi, l'archive zip ; la fabrication des aperçus (`apercu_du_document()`).
- **Notice_Archeomed_Thesaurus** : `resoudre()` (un concept, sa chaîne ascendante et ses étiquettes en toutes langues), `index_tei()`, la mise en réserve (transients d'un mois pour un concept lu, d'une heure pour un échec).
- **Notice_Archeomed_Typographie** : la correction typographique française (insécables, apostrophes, ponctuation d'un passage en langue étrangère). Corpus d'essai : `.github/essais/typographie.json`.
- **Notice_Archeomed_Controles** : les avis du dépôt. Chaque contrôle existe en PHP et en JavaScript avec les mêmes seuils et, à quelques exceptions près, les mêmes phrases ; le serveur envoie au navigateur phrases, motifs et seuils (`regles_d_accessibilite()`), pour qu'ils ne divergent pas.
- **Notice_Archeomed_Normes** et **Notice_Archeomed_Styles** : deux catalogues déclaratifs d'où se construisent les onglets de réglages et que le code lit (section 7).
- **Notice_Archeomed_Accessibilite** : contrôle axe-core lancé depuis le navigateur de l'administrateur, trace de l'audit Ara, encart « Accessibilité des illustrations » sur la fiche avec historique des corrections.
- **Notice_Archeomed_Candidats** : relevé des termes gardés hors de Pactols, statut et note de la rédaction, export CSV.
- **Notice_Archeomed_Documentation** : rend ces fichiers Markdown dans l'onglet Documentation, par un convertisseur volontairement restreint (titres, paragraphes, listes, tableaux, code, liens, gras, italique).
- **Notice_Archeomed_Relecture** : l'écran de relecture, posé sur une page publique par le shortcode `[relecture_archeomed]`, et tout ce qui l'entoure (section 16) : les deux rôles, le droit sur une notice (`droit_sur()`), le verrou, les états, le journal, les versions gardées, la tâche qui dépose les Word de relecture sur sDrive, l'onglet de réglages. La saisie elle-même se corrige dans la classe principale (`enregistrer_la_relecture()`), avec les fonctions de réception du dépôt.
- **Notice_Archeomed_Sdrive** : l'accès WebDAV (déposer, télécharger, créer un dossier, relire une taille, effacer), la liaison du site par le flux de connexion de Nextcloud, le mot de passe d'application scellé, les dossiers du compte que liste le sélecteur « Parcourir », le compte des refus du mot de passe.
- **Notice_Archeomed_Rangement** : la tâche qui range sur sDrive les fichiers des notices envoyées, sous un verrou et dans un budget de temps ; `dossier_de()` donne le dossier d'une notice, fixé au premier rangement.
- **Notice_Archeomed_Export** : la tâche qui écrit le dossier Métopes d'une rubrique dans le dossier du numéro sur sDrive, une figure par pas, et tient la liste de ce que chaque rubrique y a écrit.
- **Notice_Archeomed_Stockage** : l'onglet Stockage, la place prise catégorie par catégorie (originaux, aperçus, JPEG tirés des TIFF, autorisations, Word…), d'après les notices qui désignent chaque fichier.
- **Notice_Archeomed_Annuaire** : l'onglet Annuaire, les personnes nommées dans les notices, une ligne par adresse, ses exports pour Sympa et en CSV, le retrait et le rétablissement d'une personne, la suppression définitive d'une adresse retirée. Depuis la 3.103, `recenser()` fond (`fondre()`) les auteurs gardés d'une purge (`gardees()`) et ceux des notices privées (`des_notices()`) : la notice l'emporte pour le nom, le prénom et l'institution, et la référence gardée d'une notice remise de la corbeille cède la place à sa fiche. `garder_avant_la_purge( $jusqua )` écrit ces auteurs avant la purge ; depuis la 3.105, `garder_la_notice( $id )`, sur `wp_trash_post` et `before_delete_post`, garde ceux d'une notice privée qui part seule (la purge le suspend par `$garder_a_l_effacement`, ayant tout gardé d'avance) ; `oublier_la_gardee()` en ôte une adresse, à la suppression définitive comme à l'effacement des données personnelles.
- **Notice_Archeomed_Confidentialite** : l'export et l'effacement des données personnelles par les outils de WordPress (l'effacement ne supprime aucune notice).
- **Notice_Archeomed_Lieu_Dit** : la mise en forme du lieu-dit. Lecture d'une forme en morceaux (`morceaux()`, balises inconnues ignorées, bornée à 4 000 octets), accord au texte reçu (`accorder()`), morceaux du titre avec l'italique des normes renversé (`morceaux_du_titre()`, qu'écrivent le Word et le RTF par `runs_xml()`), HTML du titre pour les pages (`html_du_titre()`), et le script de l'éditeur d'une ligne (`script()`), commun au dépôt et à la relecture : le champ brut reste dans la page, caché, et l'éditeur le remplit à chaque frappe.
- **Notice_Archeomed_Forme** : depuis la 3.99, la mise en forme des textes d'une figure (titre, légende, description détaillée), dans le HTML restreint du lieu-dit dont elle reprend la lecture (`Notice_Archeomed_Lieu_Dit::morceaux()`, ici en plusieurs lignes). `accorder()` aligne la forme reçue sur le texte final du serveur, lettre à lettre (`aligner()`) : il admet blancs de tête, blancs regroupés, casse, point final, coupe à la borne (`BORNES` : 400, 1 500, 2 000) et « Fig. N » retapé en tête du titre ; tout autre écart fait oublier la forme, et le texte s'imprime en romain. `accorder_la_figure()` le fait pour les trois clefs d'une figure, `paragraphes()` découpe la description, `html()` écrit les morceaux pour les pages et le courriel. `script()` donne l'éditeur (`naFigureRiche()`), commun au dépôt, à la correction et à la relecture : le champ brut reste dans la page, caché, et l'éditeur le remplit à chaque frappe ; dans le champ du texte alternatif, la première ligne reste sans marque.
- **Notice_Archeomed_Nommage** : la règle des noms de fichiers (sans accent, sans espace, souligné pour séparateur) et la construction sur le modèle des réglages (`{numero}_{rubrique}_{commune}_{lieu_dit}_{annee}_Fig_{n}`).
- **Notice_Archeomed_MiseAJour** : glisse la dernière release GitHub dans le transient des mises à jour de WordPress.

## 4. Le parcours d'un dépôt

1. **Affichage.** Le shortcode (`afficher_le_formulaire()`) rend le formulaire et renvoie ses scripts en ligne au pied de page : dans le contenu, un thème à blocs changeait des `&&` en `&#038;&#038;` et le script tombait en erreur. L'intégration continue vérifie la syntaxe du script tel que la page le sert. `enqueue_assets()` ne charge Quill et la protection que sur une page qui porte le shortcode. Le navigateur garde un brouillon en `localStorage` (clé `na_brouillon_` + chemin de la page), effacé après un dépôt réussi.
2. **Saisie.** Le JavaScript du formulaire : rubrique, sous-rubrique (rubrique V seulement) et renvois se choisissent avant la nature de l'opération ; la nature se coche en cadre (`cadre[]`) et en types ou autres natures (`nature[]`), et un brouillon gardé avant la 3.107 est traduit à la reprise par `casesDAujourdhui()`, d'après la table `NATURES_D_AVANT` que sert `natures_d_avant()` ; le code Patriarche est exigé (`CONTROLES.id_patriarche`) ; un aperçu du titre se compose à mesure, la commune en capitales par le seul style (`text-transform`), pour qu'un lecteur d'écran lise un nom et non des lettres ; éditeur Quill (un seul niveau d'intertitre, écrit `h3` — le `h4` des notices anciennes y est ramené —, une liste à puces d'un niveau depuis la 3.102 — une liste collée, numérotée, y est ramenée par `naUneListeAPuces()`, et la liste ne se crée pas à la frappe d'un tiret —, gras, italique, exposant, petites capitales ; pas de notes), recherche Pactols directe depuis le navigateur (`/api/autocomplete`, `/openapi/v1/concept`), une période et un sujet au moins, lieux et précision géographique (aucune proposition ou service muet sont annoncés), figures (glisser-déposer pour réordonner, mesure de la définition JPEG et TIFF lue dans le fichier et taille d'impression qui s'en déduit — « Imprimable jusqu'à 25 × 17 cm environ, à 300 ppp » —, vignette d'un TIFF ou d'un PDF dessinée par le navigateur et agrandie d'un clic, TIFF trop lourd passé en JPEG sur place, figure envoyée à part — une carte sans fichier, avec le lien de transfert, sa date d'expiration et le nom du fichier —, titre, crédits, nature et texte alternatif obligatoires, autorisation de reproduction, un champ unique « Pour un public empêché de voir » dont le premier paragraphe est le texte alternatif et la suite la description détaillée, et la nature de la figure — `illus_type`, obligatoire — qui adapte le conseil et les avis), avis annoncés au lecteur d'écran une fois paraître (rafale regroupée, jamais redits), avis en direct (ceux du texte une seconde après la dernière frappe, le paragraphe où se trouve le curseur exempté de l'avis de ponctuation tant qu'on y écrit — sauf juste après un collage, où l'avis reste —, les lignes courtes sans ponctuation réunies en un seul avis quand elles se suivent (`lignesCourtes()`, comme `Notice_Archeomed_Controles::lignes_courtes()`, depuis la 3.101), l'appel mal écrit « (figure 3) » pour « (fig. 3) » compris ; les adresses électroniques et web vérifiées dès qu'on quitte leur champ, et dès la frappe, depuis la 3.100, une adresse électronique qui porte un caractère qu'aucune ne porte (`horsDUneAdresse()` : blanc, « ; », « , », « : », « < », « > », parenthèses, crochets, barre oblique inverse, guillemet droit, second « @ ») ; les autres à la sortie du champ), compteur de mots, récapitulatif avant envoi qui sépare « À corriger » (les erreurs) de « À vérifier avant l'envoi » (les avis bloquants, que lève une case de confirmation).
3. **Envoi.** Un POST classique (multipart) vers la page elle-même. `handle_submission()` est accroché à `init` et ne fait rien sans le champ `notice_archeomed_envoi`.
4. **Contrôles d'entrée**, dans l'ordre : forme des champs (`entrees_attendues()`), jeton `notice_archeomed_submit`, pot de miel `na_website`, délai minimal de trois secondes (`na_ts`), quota de tentatives par adresse IP, protection (puzzle et/ou Turnstile). Chaque refus est noté avec sa cause (`noter_le_refus()`, option `na_derniers_refus`) et se lit dans l'onglet Hébergement.
5. **Collecte et nettoyage** de chaque champ, bornes de longueur (`limit_string()`, qui note les coupes pour l'auteur), normalisations (année, lieu-dit sans point final, département sans parenthèses), termes Pactols (ARK vérifiés par `Thesaurus::ark_propre()`, un terme sans ARK est marqué `libre`), texte HTML nettoyé (`clean_richtext()`).
6. **Champs obligatoires** (`champs_manquants()`, période et sujet compris), quota d'envois, place sur le disque (`garder_le_disque()` : au-delà du seuil, la notice est reçue sans ses fichiers, chaque figure devenant « envoyée à part »), téléversements (`handle_uploads()` : extension, type MIME réel, PDF valide, plafonds), JPEG tirés par le navigateur (`recevoir_les_conversions()`) et autorisations.
7. **Inscription.** `Notice_Archeomed_File::deposer()` crée le post `na_notice` (privé) et ses métadonnées, pose la marque `_na_preparation`, puis l'état `en_attente` — dans cet ordre, pour qu'aucune relance ne la prenne pendant sa préparation. La référence est tirée au hasard (six caractères).
8. **Préparation**, dans la requête de l'auteur : les illustrations sont rangées à demeure (`archiver_les_illustrations()`), les JPEG tirés par le navigateur et les autorisations aussi (`_na_conversions`, `_na_autorisations`), puis la marque `_na_preparation` est ôtée. Si la requête meurt, la marque vieillit (`ABANDON` = 600 s) et la file reprend la notice. Depuis la 3.90, en envoi différé, ni les versions allégées ni le Word ne se fabriquent ici : une planche en PDF y coûtait une quinzaine de secondes et l'un des cinq processus du serveur. La tâche `na_fabriquer_les_apercus` les fait juste après (par Imagick, sinon par GD à partir d'un JPEG, d'un PNG ou d'une conversion du navigateur), et la file refait le Word d'après la saisie gardée (`refaire_le_document()`). En envoi immédiat, aperçus et Word se fabriquent encore dans la requête (`fabriquer_le_document()`).
9. **Retour à l'auteur.** En envoi différé (le défaut), la tâche des aperçus puis `programmer()` réservent la suite, et l'auteur est redirigé vers le panneau de fin, avec sa référence. En envoi immédiat, l'expédition a lieu dans la requête, protégée par un `try/catch` : une erreur est consignée, jamais montrée à l'auteur, et la relance reprend la notice.
10. **En cas de refus** (champ manquant, fichier refusé…), `redirect_result()` garde la saisie en transient (`na_reprise_<jeton>`) et redirige avec `notice_envoyee=0`, `notice_erreur`, `notice_champ(s)` et `notice_reprise` : le formulaire se rouvre rempli, les fichiers seuls sont à redéposer.
11. **Base indisponible.** Si l'inscription échoue, la notice part directement par courriel avec ses originaux, pour ne pas la perdre.

## 5. La file d'attente et l'expédition

### États (`_na_etat`)

| État | Sens |
|---|---|
| `en_attente` | inscrite, à expédier |
| `en_cours` | prise par un passage ; `_na_prise` porte l'heure de la prise |
| `envoyee` | le courriel de la rédaction est parti ; `_na_note` dit ce qui manquait éventuellement |
| `echec` | `ESSAIS_MAX` (5) essais sans succès ; `_na_erreur` dit pourquoi ; relance à la main |

### La prise atomique

`Notice_Archeomed_File::prendre()` est le cœur de la règle « deux dépôts ne se gênent pas ». Elle n'écrit pas après avoir lu : elle fait une seule requête conditionnelle, `UPDATE … SET meta_value='en_cours' WHERE … AND meta_value='en_attente'`, et seul le passage dont la requête a modifié une ligne expédie. Une prise abandonnée (plus de `ABANDON` secondes) se reprend de la même façon, par une écriture conditionnelle sur `_na_prise`. **Ne jamais remplacer cela par un `get_post_meta` suivi d'un `update_post_meta`.**

### Expédition (`expedier_de_la_file()`)

Refuse une notice en préparation, fait fabriquer d'abord les versions allégées manquantes (tâche à part, une seule fois : `_na_apercus_tente`), attend la tâche tant qu'elle travaille (`_na_apercus_en_cours`, abandonnée au bout de 600 s), prend la notice, refait le Word s'il a disparu, puis `expedier()` :

- un seul courriel pour toute la rédaction (destinataires « notices » des réglages), `Reply-To` vers le responsable ; l'objet nomme la commune, le département, le lieu-dit et l'année, préfixé `[CORRECTION de …]` ou `[CORRIGÉE]` s'il y a lieu ; le titre du corps met la commune en capitales, comme la revue, et le corps dit qui a déposé (« Déposée par ») ;
- pièces composées par `pieces_du_courriel()` : le Word toujours, puis chaque figure dans son ordre, en version allégée si elle en a une, l'original sinon, sous le nom de sa figure (clé du tableau d'attachements de `wp_mail`), tant que le poids réglé n'est pas atteint (`budget_du_courriel()`, en méga-octets décimaux, encodage base 64 compté) ; ce qui ne tient pas est nommé dans un encadré avec le lien de la notice ;
- `figures_dans_le_corps()` pose ensuite les aperçus JPEG, PNG ou GIF dans le corps, au-dessus de leur légende (`<img src="cid:…">`, 600 px de large au plus, images jointes en ligne sur `phpmailer_init` ; sur le même crochet, en dernier, `nommer_l_expediteur()` pose le nom réglé — « Revue Archéologie médiévale » par défaut — sur les seuls courriels envoyés par `Notice_Archeomed_Pactols::courriel()`) ; un TIFF reste en pièce jointe, et une notice composée avant cette marque garde ses figures jointes ; le Word et les autorisations restent des pièces jointes ; une figure envoyée à part n'a pas d'aperçu : le courriel donne son lien ;
- sous la notice, le lien de correction de la rédaction et « Écrire aux responsables de la notice », un message adressé d'avance à toutes les adresses de la notice ;
- si l'auteur a donné un lien de transfert (figure envoyée à part, ou lien vers ses originaux), un courriel d'alerte à part (`alerter_pour_les_originaux()`), dont l'objet dit le délai et la date d'expiration la plus proche ; son échec est consigné sans faire échouer la notice ;
- puis une copie à chaque personne citée — responsable, co-responsable, co-auteur, déposant —, avec le Word et le lien de correction (valable un mois, vers la dernière page publiée qui porte le formulaire) ; le sort de chaque copie, partie ou refusée avec sa raison, est gardé dans `_na_copies` et se lit sur la fiche.

Rendu vrai : état `envoyee`, note, effacement du Word temporaire, puis rangement sur sDrive réservé (`Notice_Archeomed_Rangement::programmer()`). Rendu faux : `compter_un_essai()`. L'erreur réelle de PHPMailer est captée par `wp_mail_failed` et traduite pour la rédaction (`expliquer_l_erreur()`).

### Tâches planifiées (WP-Cron)

WordPress inscrit un envoi par une lecture puis une écriture de son tableau de tâches : des dépôts simultanés en perdent (l'essai de charge de l'intégration continue en a montré : vingt-et-une inscriptions sur vingt-cinq). Ce n'est pas une perte de notice : c'est la relance périodique (`na_relancer_les_notices`, toutes les cinq minutes, quinze notices et vingt secondes au plus par passage) qui expédie celles dont l'inscription a été perdue.

| Crochet | Fréquence | Rôle |
|---|---|---|
| `na_expedier_notice` (argument : id) | ponctuelle | expédier une notice |
| `na_relancer_les_notices` | toutes les cinq minutes (intervalle `na_cinq_minutes` ajouté par l'extension) | reprendre jusqu'à `PAR_RELANCE` (15) notices en attente, en vingt secondes au plus, en respectant le délai d'une notice déjà manquée (`_na_pas_avant`, cinq minutes par essai) ; remettre en route la tâche des termes. `PAR_PASSAGE` (5) ne sert qu'au bouton « Expédier maintenant » |
| `na_recapituler_les_notices` | chaque jour à 7 h, heure du site | récapitulatif adressé aux destinataires « recap », les notices dans l'ordre du volume avec leur état |
| `na_fabriquer_les_apercus` (argument : id) | ponctuelle | versions allégées manquantes, puis reprogrammation de l'envoi |
| `na_resoudre_les_termes` | tâche unique, réservée au besoin | lire dans Pactols les termes des notices en attente, par lots (`LOT_TERMES` = 50) et dans un budget de `BUDGET_TERMES` (10 s) |
| `na_ranger_sur_sdrive` | tâche unique, réservée au besoin | ranger sur sDrive les notices envoyées ; un échec propre à la notice lui coûte un essai (`ESSAIS` = 8), une panne de sDrive (injoignable, 429, 507) arrête le passage sans en coûter et le reprend un quart d'heure plus tard, une heure si le compte est plein ; un passage coupé par le serveur compte comme un essai (`_na_sdrive_entame`) |
| `na_exporter_sur_sdrive` | tâche unique, réservée au besoin | écrire le dossier Métopes d'une rubrique dans le dossier du numéro sur sDrive, une figure par pas, une rubrique à la fois (`na_export_attente`) ; une figure ou une rubrique est abandonnée après cinq passages manqués (`ESSAIS`) ; une figure sur laquelle le serveur a coupé deux passages (`COUPURES`) est laissée aussitôt, et le lisez-moi la donne à reprendre ; après une mise à jour du plugin, l'export en cours oublie ses délais et ses coupures et reprend aussitôt (`version` dans `na_export`) |
| `na_deposer_les_relectures` | tâche unique, réservée au besoin | déposer sur sDrive les Word de relecture qui attendent ; même règle que le rangement : un refus propre à la notice lui coûte un essai (`ESSAIS` = 8), une panne de sDrive arrête le passage sans en coûter |
| `na_mesurer_le_disque` | tâche unique, au plus tous les quarts d'heure | remesurer le disque pour l'estimation que lit le dépôt |

`activer()` pose les tâches périodiques, `desactiver()` les retire. `verifier_les_rendez_vous()` les repose si WordPress les a perdues. Si `DISABLE_WP_CRON` est actif sans tâche système de remplacement, la file s'allonge : un bandeau le signale, et l'envoi immédiat reste possible dans les réglages.

## 6. Les données

### Le type de contenu

`na_notice` : non public, sans interface d'édition (on lit une notice, on ne la rédige pas), statut toujours `private` (filtres `wp_insert_post_data` et `wp_untrash_post_status`). Le titre de la liste est calculé (`titre_de_liste()` : lieux, lieu-dit, année).

### Métadonnées d'une notice

| Clé | Contenu |
|---|---|
| `_na_donnees` | la saisie complète, tableau sérialisé (voir ci-dessous) |
| `_na_notice` | le HTML de la notice, tel que le courriel l'a porté |
| `_na_reference` | la référence du dépôt (six caractères) |
| `_na_remplace` | la référence que ce dépôt corrige, s'il en corrige une (liens de correction d'avant la 3.69) |
| `_na_version` | le numéro de version de la notice ; une correction ne passe que par une écriture conditionnelle qui l'avance d'un (`changer_de_version()`) |
| `_na_versions` | l'histoire des vingt dernières corrections : `version`, `le`, `par`, `champs` |
| `_na_corrigee` | les points changés par une correction de l'auteur, à signaler à la rédaction (`[CORRIGÉE]` dans l'objet) ; effacée une fois le courriel parti |
| `_na_etat`, `_na_prise`, `_na_essais`, `_na_erreur`, `_na_note`, `_na_envoyee_le` | la file |
| `_na_pas_avant` | l'heure avant laquelle une notice déjà manquée n'est pas reprise (cinq minutes par essai) |
| `_na_copies` | le sort des copies envoyées aux auteurs : `le`, puis par adresse `a`, `partie`, `raison` |
| `_na_preparation` | marque posée pendant la préparation du dépôt |
| `_na_fichiers` | les fichiers à joindre (chemins) |
| `_na_document` | le Word temporaire, effacé après l'envoi (il se refait à la demande) |
| `_na_illustrations` | les originaux rangés, dans l'ordre des figures |
| `_na_conversions` | les JPEG tirés par le navigateur d'un TIFF ou d'un PDF (haute et basse définition), par rang |
| `_na_autorisations` | les autorisations de reproduction, par rang de figure |
| `_na_apercus`, `_na_apercus_tente`, `_na_apercus_en_cours` | les versions allégées par rang, la marque de leur fabrication, et celle du travail en cours (la file attend qu'elle se lève) |
| `_na_rubrique`, `_na_famille`, `_na_classement`, `_na_responsable`, `_na_courriel` | copies à plat pour trier, filtrer et chercher dans la liste |
| `_na_pactols` | les concepts lus dans Pactols, par ARK (forme préférée, étiquettes, chaîne, date de lecture) |
| `_na_pactols_apres`, `_na_pactols_essais` | l'attente de résolution des termes |
| `_na_historique_figures` | les textes d'accessibilité remplacés par une correction depuis la fiche |
| `_na_relecture_etat` | l'état de relecture : `a_relire` (défaut), `relue`, `a_soumettre`, `soumise`, `accord`, `validee` (figée : rien ne la corrige) |
| `_na_relecture_avant_validation` | l'état d'avant la validation, que la réouverture rend ; effacée avec elle |
| `_na_relecture_verrou` | qui tient la notice ouverte : « heure:compte » ; périmé au bout de `DUREE_DU_VERROU` (300 s) |
| `_na_relecture_version` | une ligne par version gardée : `version`, `le`, `par`, `par_id`, `champs`, `donnees` (la saisie entière) |
| `_na_relecture_journal` | une ligne par événement : `le`, `par`, `par_id`, `quoi`, `etat_avant`, `etat_apres`, `remarque`, `version` |
| `_na_sdrive` | la table du rangement : chemin du serveur ⇒ copie sur sDrive, vérifiée par sa taille |
| `_na_sdrive_dossier` | le dossier de la notice sur sDrive, fixé au premier rangement |
| `_na_sdrive_apres`, `_na_sdrive_essais`, `_na_sdrive_raison`, `_na_sdrive_entame` | l'attente du rangement, ses essais, la raison du dernier échec, la marque d'un passage commencé |
| `_na_sdrive_a_deposer` | l'empreinte du fichier `a-deposer.txt` déjà écrit, qui rappelle les figures envoyées à part |
| `_na_relecture_a_deposer`, `_na_relecture_apres`, `_na_relecture_essais`, `_na_relecture_raison`, `_na_relecture_deposes` | l'attente du dépôt des Word de relecture sur sDrive, et ce qui y est déjà |

### La saisie (`_na_donnees`)

Clés principales : `rubrique_principale`, `renvoi_1`, `renvoi_2`, `rubrique_matiere` (la sous-rubrique de la rubrique V ; la clé garde son ancien nom), `renvoi_matiere` (la sous-rubrique de V où se range le renvoi d'une notice d'une autre rubrique, posée à la relecture par qui relit V ou par la direction) ; `lieux` (liste `{nom, ark}`, le premier est la commune, recopiée dans `commune` et `commune_ark`), `departement`, `lieu_dit`, `annee` ; `lieu_dit_forme` (depuis la 3.97, la mise en forme du lieu-dit en HTML restreint — `em`, `sup`, `span class="na-pc"` — accordée au texte de `lieu_dit` par `Notice_Archeomed_Lieu_Dit::accorder()` : capitale initiale, point final ôté ; absente ou vide quand elle ne marque rien ou ne dit pas le même texte) ; `nature` et `nature_items` (`{label, ark}` ; depuis la 3.107, les termes recomposés du cadre et du type — « Fouille préventive » —, de sorte qu'une notice reçue avant garde la même forme ; la relecture compare les cases à l'état recomposé par `recomposer_les_natures()` et ne réécrit rien quand on n'y a pas touché) ; `pactols_periods_items`, `pactols_subjects_items`, `pactols_places_items` (`{label, ark, idConcept, fullpath}`, plus `libre` pour un terme gardé hors de la liste) et leurs versions texte ; `num_autorisation`, `id_patriarche`, `rapport_lien`, `donnees_lien` (depuis la 3.98, le lien facultatif du jeu de données), `originaux_lien`, `originaux_jusqua` (AAAA-MM-JJ) ; `organismes` ; responsable, co-responsable et co-auteur (`*_prenom`, `*_nom`, `*_inst`, `*_email`) ; déposant (`deposant_prenom`, `deposant_nom`, `deposant_email` ; le nom n'est demandé que depuis la 3.81) ; `texte_notice` (HTML restreint : `p`, `h3` pour les intertitres — un `h4` ancien y est ramené à la lecture —, `ul` et `li` pour la liste à puces, à un seul niveau, depuis la 3.102, `br`, `em`, `strong`, `sup`, `sub`, `span` de petites capitales) ; `illustrations` (par figure : `rang`, `titre`, `legende`, `credits`, `alt`, `description`, `titre_forme`, `legende_forme`, `description_forme` (depuis la 3.99, la mise en forme de chacun de ces trois textes, dans le HTML restreint du lieu-dit, lignes de la description jointes par `br`, accordée au texte par `Notice_Archeomed_Forme::accorder()` ; vide quand elle ne marque rien ou ne dit pas le même texte ; le texte alternatif n'en a jamais), `type`, `autorisation`, pixels, et pour une figure envoyée à part `a_part` : `lien`, `jusqua`, `fichier`, plus `refusee` quand c'est le manque de place qui l'a voulue) ; `commentaires` ; `remplace`.

Une figure retirée à la relecture reste dans `illustrations`, à son rang, avec une clé `masquee` (`motif`, `precision`, `par`, `le`) : `preparer_la_saisie()` l'ôte de tout document et numérote les autres à la suite, `notices_a_assembler()` écarte ses fichiers du dossier.

Les sorties ne lisent jamais `_na_donnees` directement : elles passent par `saisie_de()`, qui ajoute les formes préférées, les aperçus et ce que chaque époque de la saisie demande (`lieux_de()`, `organismes_de()` relisent les notices anciennes). Quand une version change la forme des données, `rattraper_les_anciennes()` met les notices à niveau une fois (`ETAT_DES_DONNEES`, option `na_etat_des_donnees`).

### Options

| Option | Contenu |
|---|---|
| `notice_archeomed_options` | tous les réglages (section 7), y compris les sous-clés `normes` et `styles` |
| `notice_archeomed_accessibilite` | le dernier contrôle axe-core et le dernier audit Ara |
| `notice_archeomed_candidats` | statut et note par terme candidat |
| `notice_archeomed_relecture` | la relecture : `close` (campagne close) et `coordinateurs` (rubrique ⇒ comptes) |
| `na_etat_des_donnees` | version de la forme des données |
| `na_essais_de_poids` | l'historique des essais de poids du courriel |
| `na_sdrive` | la liaison à sDrive faite depuis l'onglet Hébergement (serveur, nom de connexion, identifiant interne, mot de passe d'application scellé, dossier) |
| `na_sdrive_etat` | ce qu'a fait le dernier passage du rangement, ou la panne qui l'a arrêté |
| `na_sdrive_verrou`, `na_export_verrou` | les verrous du rangement et de l'export, pris par une écriture qui ne réussit qu'une fois, périmés au bout de cinq minutes |
| `na_export`, `na_export_attente`, `na_export_manifestes` | l'export en cours, les rubriques qui attendent leur tour, et ce que chaque rubrique a écrit dans chaque numéro |
| `na_sdrive_refus` | les refus du mot de passe sDrive (401) reçus de suite ; à trois (`REFUS_MAX`), le rangement s'arrête jusqu'à une nouvelle liaison ou « Ranger maintenant » |
| `na_disque_connu`, `na_disque_recu` | la dernière mesure du disque, et ce que les dépôts ont reçu depuis |
| `na_derniers_refus` | les vingt derniers envois refusés : heure, raison, cause, âge de la page, connexion |
| `na_annuaire_retires` | les adresses retirées de l'annuaire |
| `na_annuaire_supprimes` | les empreintes des adresses supprimées définitivement de l'annuaire |
| `na_annuaire_gardees` | depuis la 3.103, les auteurs des notices purgées — et depuis la 3.105 des notices supprimées —, que l'annuaire continue de montrer |
| `na_page_<md5 du jeton>` | depuis la 3.104, une page qui envoie en arrière-plan (`na_page`, en-tête `X-NA-Envoi`) : `heure\|` tant que l'envoi est en route, `heure\|adresse du résultat` ensuite. Prise par `INSERT IGNORE` une fois les contrôles passés ; périmée en route au bout de `ATTENTE_D_UN_ENVOI` secondes, effacée au bout d'un jour. Une sonde (`na_sonde`) lit la réponse sans rien renvoyer : `{redirect}`, `{attendre}` ou `{absente}` |

### Transients

`na_reprise_<jeton>` (saisie gardée pour la reprendre, un mois au plus), `na_reprise_faite_<jeton>`, `na_turnstile_injoignable` (Cloudflare n'a pas répondu : le puzzle s'ajoute), `na_pactols_<hash>` (réserve de l'autocomplétion relayée par le serveur), réserves du thésaurus (concept lu : un mois ; échec : une heure), compteurs de quotas, `na_essai_<utilisateur>` (résultat d'un essai des réglages), `na_diagnostic_*`, `na_assemblage*`, `na_maj_release` (dernière release GitHub, six heures), `na_relecture_message_<compte>` (le message de l'écran de relecture après un geste), `na_relecture_vue_<compte>` (le regard de coordinateur que ce compte emprunte : `{ sorte, valeur }`, deux heures).

### Fichiers

Les originaux, autorisations, versions allégées et une éventuelle feuille de styles déposée vivent dans `wp-content/uploads/notice-archeomed/`, protégé par un `.htaccess` (`Require all denied`) et un `index.php`. Ils ne se servent que par `admin-post` (`na_illustration`), après vérification du droit et du jeton. Les fichiers de travail passent par le répertoire temporaire (`notice-archeomed-tmp/`, ateliers de dossiers) et sont purgés : un jour en général, un mois pour ce qu'un envoi manqué a mis de côté. Supprimer une notice efface ses fichiers (`before_delete_post`).

Relié à sDrive, le serveur ne garde d'une notice envoyée que ses aperçus : originaux, JPEG tirés par le navigateur et autorisations partent dans son dossier sur sDrive, avec le Word de chaque version, et la copie du serveur ne s'efface qu'une fois la taille du dépôt relue chez sDrive et le chemin retenu dans `_na_sdrive`. Un téléchargement depuis la fiche va alors chercher le fichier sur sDrive.

## 7. Réglages

### Priorité des valeurs

Pour chaque clé : une constante de `wp-config.php` si elle existe (`NA_TURNSTILE_SECRET`, `NA_TURNSTILE_SITE`, `NA_DEST_EMAIL`, `NA_MODE_ENVOI`, `NA_PROTECTION`), sinon la valeur enregistrée, sinon le défaut du code (`Notice_Archeomed_Settings::$defaults`). Un champ verrouillé par une constante est grisé dans la page.

### Onglets

Destinataires · Numéro et iconographie · Normes éditoriales · Styles Métopes · Formulaire · Accessibilité · Candidats à Pactols · Relecture · Courriel · Feuille de styles · Mises à jour · Hébergement · Stockage · Annuaire · Documentation. Chaque onglet a son propre formulaire. L'onglet Hébergement a pour clé `diagnostic`, son ancien nom : il dit l'état du serveur, relie le site à sDrive (« Relier le site à sDrive », « Parcourir », « Ranger maintenant »), montre où en sont rangement et export, et liste les derniers envois refusés. Stockage dit la place prise par catégorie et les notices les plus lourdes, et purge les notices (depuis la 3.103 : `purger_les_notices( $jusqua, $mot )` met à la corbeille les notices privées d'identifiant au plus `$jusqua`, après `Notice_Archeomed_Annuaire::garder_avant_la_purge()` et `Notice_Archeomed_Export::vider_la_file()` ; `vider_la_corbeille_des_notices( $mot, $oublier_les_noms )` les efface, et rend sur demande les noms de dossier par `Notice_Archeomed_Rangement::liberer_les_noms_orphelins()` ; l'une et l'autre prennent les verrous du rangement, de l'export et du dépôt des relectures, et s'arrêtent au bout de `BUDGET_DE_LA_PURGE` secondes) ; Annuaire liste les personnes, s'emporte pour Sympa ou en CSV, retire ou rétablit une personne, et supprime définitivement une adresse retirée. Un onglet est une méthode `onglet_<clé>()` de la classe des réglages ; en ajouter un, c'est ajouter sa clé dans `onglets()` et sa méthode.

### Clés principales de `notice_archeomed_options`

- **Destinataires** : `destinataires` (liste d'adresses, chacune cochée pour « notices » et/ou « recap »).
- **Protection** : `protection` (`locale`, `turnstile`, `les_deux`), `turnstile_site`, `turnstile_secret`.
- **Envoi** : `mode_envoi` (`differe`, `immediat`), `envoi_mode` (`php` ou `smtp`) et `smtp_*`, `poids_courriel` (Mo).
- **Iconographie** : `numero`, `nom_modele`, `br_*` (basse définition : côté le plus long — 2 500 px —, ppp — 150 —, poids — 1 000 Ko —, tolérance — 10 %), `hr_*` (haute définition des photographies et des traits), `garder_originaux`.
- **Pactols** : `pactols_serveur` (`oui`/`non` : le serveur lit-il les termes).
- **Mises à jour** : `maj_github`, `github_depot`, `github_jeton`.
- **`normes`** : les choix éditoriaux, décrits par `Notice_Archeomed_Normes::catalogue()` — siècles (`siecle_mot`, `siecle_chiffres`, `siecle_ordinal`), typographie (`typographie`, `espace_ponctuation`, `annees`), composition (`titre_notice`, `lieu_dit_italique`, `figure_abreviation`, `figure_numero`), dépôt (`photo_largeur_cm`, `photo_hauteur_cm`, `photo_ppp`, `trait_ppp`, `mots_min`, `mots_max`, `alt_conseille`), `sigles` (liste « SIGLE = développement »), `avis` (les familles d'avis données au dépôt). Le code lit une norme par `Notice_Archeomed_Normes::valeur( 'cle' )`.
- **`styles`** : la correspondance de chaque bloc de la notice avec un style de la feuille, décrite par `Notice_Archeomed_Styles::catalogue()`. Le code n'écrit jamais un nom de style en dur : il appelle `Notice_Archeomed_Styles::de( 'bloc' )`. Un choix est refusé s'il n'existe pas dans la feuille installée.

`sanitize()` ne repart pas de zéro : il complète l'option existante, onglet par onglet (les témoins `*_presents` disent qu'un onglet a été soumis), et se protège du double passage que WordPress fait quand l'option n'existe pas encore.

## 8. Le document Word

### Principe

On ne recrée pas la feuille de styles : `Notice_Archeomed_DOCX` ouvre le gabarit (`modele-metopes.docx`, ou celui déposé dans l'onglet Feuille de styles), recopie toutes ses pièces et ne remplace que `word/document.xml`, les relations, le manifeste des types et les propriétés du document. Les styles sont désignés par leur **nom affiché** (« TEI_figure_caption ») et résolus vers l'identifiant interne en lisant `styles.xml`. Remplacer le gabarit suffit donc à suivre une évolution de Métopes. Le style « à supprimer » n'est pas dans la feuille : il est injecté à l'écriture ; il porte tout ce que la rédaction doit lire mais qui ne doit pas être publié (avis, termes non retenus, description d'une figure à placer…).

### API du générateur

`add_paragraph( $style, $runs )` où chaque run est un tableau : `text`, et des drapeaux `b`, `i`, `sup`, `sub`, `pc` (petites capitales), `brut` (aucune typographie : identifiants), `nom` (insécables dans un nom de personne), `sans_siecles`. `add_raw_paragraph( $style, $xml )`, `add_list_item( $style, $xml )` (depuis la 3.102 : le paragraphe sous son style et la puce de Word, `w:numPr` ; la définition de la puce s'ajoute à `word/numbering.xml` à l'écriture, avec un numéro au-delà de ceux du modèle, et seulement si le document a une liste), `char_run( $style, $texte )`, `hyperlink( $url, $libellé )`, `image_liee()` (image en lien vers `../icono/br/…`, dans le dossier Métopes) et `image_incluse()` (octets dans le fichier, pour le Word envoyé par courriel), `html_to_paragraphs()` (le HTML de l'éditeur en paragraphes), `write_to( $chemin )`.

### Ce que le générateur fait au texte

- **Typographie française** (`Notice_Archeomed_Typographie::corriger()`), selon les normes : insécables, apostrophes courbes, passages en langue étrangère laissés à leur ponctuation, adresses et heures protégées.
- **Siècles** (`DOCX::siecles()`) : « XIIe siècle » devient le chiffre en bas de casse avec l'attribut petites capitales, l'ordinal en exposant, « siècle » abrégé en « s. », selon les normes. Word ne réduit que les minuscules : c'est pourquoi le chiffre s'écrit en bas de casse. Les sorties HTML écrivent au contraire le chiffre en capitales, réduit par `font-variant-caps: all-small-caps`, pour que la synthèse vocale lise un nombre.
- **Caractères interdits en XML** ôtés à l'échappement (`esc()`).
- **Texte alternatif** : dans `descr` du `wp:docPr` de chaque image ; c'est là que Métopes le lit. Depuis la 3.98, aussi dans `title`, que LibreOffice et Word affichent comme texte alternatif. Le paragraphe `figure_alttext` est facultatif et désactivé par défaut (la conversion Métopes le perd).
- **Description détaillée** : en fin de légende, dans le style du bloc `figure_description` (`TEI_figure_caption` par défaut), ouverte par « Description : ».
- **Langue** : la feuille est déclarée en français ; propriétés du document (titre, dates) renseignées.

### Ordre d'une notice

Avis éventuels (« à supprimer ») ; titre (lieux, département, lieu-dit) ; métadonnées (nature, autres lieux, période, année, numéro d'autorisation, identifiant Patriarche, rapport, jeu de données, organismes, mots-clés — chaque terme Pactols porte son ARK en lien) ; texte ; responsabilités (collées au dernier paragraphe ou dans leur paragraphe, selon le réglage `responsabilites` ; elles portent le point final de la notice, dans la parenthèse, sauf si la dernière institution finit déjà sur un point, un « ? », un « ! » ou « … ») ; figures (début de bloc, image, titre avec numéro, légende, description, crédits, autorisation, texte alternatif éventuel, fin de bloc). `remplir_le_document()` est la seule fonction qui écrit une notice : le Word d'une notice, le fascicule et le dossier l'appellent tous.

### Fascicule, relecture et dossier Métopes

- **Fascicule** (`na_fascicule`) : toutes les notices d'une rubrique dans l'ordre du volume (`classement_de()` : rubrique, sous-rubrique pour la V, famille d'opérations, commune), avec les renvois des autres rubriques à leur place alphabétique.
- **Page de relecture** : la même rubrique en HTML, figures comprises, pour relire dans un navigateur.
- **Dossier Métopes** (`na_paquet`, `Notice_Archeomed_Paquet`) : une archive zip avec `style/` (le Word, images liées), `XML/` (fichier d'indexation), `icono/hr` (haute définition aux normes), `icono/br` (basse définition liée au Word), `icono/originaux`, et un lisez-moi qui dit tout ce qui n'a pas pu être fait. Les images sont nommées par `Notice_Archeomed_Nommage::construire()`.
- **Export sur sDrive** (`na_export`, `Notice_Archeomed_Export`) : le même dossier, écrit sans archive dans le dossier du numéro, à côté de `originaux/`, par la tâche `na_exporter_sur_sdrive`, une figure après l'autre. Un nouvel export n'efface que ce que la rubrique y avait écrit (son manifeste), jamais `originaux/` ni ce qu'une autre rubrique a posé ; la liste des notices dit où il en est.
- **Figure envoyée à part** : le Word porte à la place de l'aperçu un cadre rouge « à remplacer par l'original envoyé à part » ; le fichier `a-deposer.txt` du dossier de la notice sur sDrive rappelle, figure par figure, ce que la rédaction doit y déposer.
- **Blocs d'index** (`indexation_de()`) : pour chaque terme lu, l'arbre des concepts du plus général au terme, avec étiquettes en toutes langues, en TEI, dans une enveloppe d'un espace de noms propre à l'extension (constante `ESPACE_INDEXATION`) qui nomme le style du paragraphe où coller chaque zone.

## 9. Pactols

- **Dans le navigateur** : la recherche interroge directement `pactols.frantiq.fr` (CORS ouvert) ; un relais serveur existe aussi (`wp_ajax_na_pactols_search`, réserve d'un jour, quota par connexion). Un terme choisi garde son ARK ; un terme tapé hors de la liste est gardé, marqué `libre`, et n'est pas retenu dans la notice (il paraît « à supprimer » et dans l'onglet Candidats à Pactols).
- **Sur le serveur** : `Notice_Archeomed_Thesaurus::resoudre()` lit le concept par l'API OpenAPI (`/openapi/v1/concept/…`, en-tête `Accept: application/json` obligatoire), son expansion vers le haut, ses étiquettes. Un concept sans chaîne n'est pas résolu ; un échec se retient une heure, pour toutes les notices ; une coupure due au budget n'est pas un échec et se reprend. Les concepts retirés du thésaurus ne sont jamais indexés et leur remplaçant est signalé.
- **Lecture coupable** : si l'hébergement refuse la sortie vers Pactols (proxy), le réglage `pactols_serveur` = `non` coupe la lecture ; les ARK restent dans le Word, et une transformation XML peut en tirer les entrées d'index.

## 10. Le formulaire, côté navigateur

Tout le JavaScript est en ligne dans `render_form()`, sans outil de construction, et doit rester compatible avec les navigateurs courants sans transpilation (ni `?.`, ni assertion arrière dans les expressions régulières). Les données du serveur arrivent par `wp_json_encode` : normes, phrases et motifs des avis (`A11Y`), plafonds réels de PHP. Les avis du navigateur et ceux du serveur doivent rester identiques : **toute modification d'un contrôle se fait des deux côtés**, et les essais comparent les phrases.

Accessibilité : chaque champ a son étiquette et son `aria-describedby`, les erreurs sont listées et reliées à leur champ, les états ne reposent, en règle générale, pas sur la seule couleur (un point connu : l'état actif de la barre d'outils de l'éditeur), le puzzle se manœuvre au clavier. L'onglet Accessibilité fait passer la page publiée à axe-core. Ces points sont pris en compte à la conception ; **ils n'ont pas fait l'objet d'un audit RGAA complet** ni d'essais systématiques avec des utilisateurs de lecteurs d'écran.

## 11. Sécurité

- Toute action d'administration passe par `admin-post` ou `admin-ajax` avec un jeton (`check_admin_referer`, `wp_verify_nonce`) et une capacité (`na_regler`, que `manage_options` emporte, ou le droit de relire).
- Entrées : chaque champ est ramené à sa forme (`entrees_attendues()`), nettoyé, borné ; les URL limitées à `http`/`https` ; les ARK au seul domaine de Frantiq ; les téléversements contrôlés par extension, type MIME réel et validité du PDF.
- Sorties : échappement systématique (`esc_html`, `esc_attr`, `esc_url`) ; échappement XML propre au DOCX et au TEI ; cellules CSV protégées contre les formules.
- Fichiers reçus hors de la portée du web ; servis seulement par l'extension.
- Quotas par IP et par adresse ; pot de miel ; délai minimal ; puzzle signé ; Turnstile au choix.
- Le seul script tiers côté visiteur est Turnstile (Cloudflare), qui n'admet pas d'empreinte, selon la protection choisie ; Quill est servi par le site, axe-core (administration seulement) porte son empreinte SRI.
- Aucune donnée d'auteur dans une URL, sauf le jeton de reprise.

## 12. Développer et éprouver

### Contrôles locaux

```sh
./verifier-la-syntaxe notice-archeomed/*.php   # syntaxe, doublons de classes, commentaires décrochés
./verifier-les-styles                         # après un changement de modele-metopes.docx
./empaqueter                                  # l'archive à téléverser
```

`verifier-la-syntaxe` est un filet écrit en Python pour un poste sans PHP ; il ne remplace pas `php -l`. `.github/essais/verifier-les-doublons` éprouve le filet lui-même sur des cas connus.

### Les essais

`.github/essais/file-des-termes.php` est un script WP-CLI (`wp eval-file`) qui charge l'extension dans un vrai WordPress et vérifie environ deux cents comportements : file et prise atomique, pièces du courriel, document Word (styles, ordre, XML valide), typographie (corpus `typographie.json`), siècles, normes, styles, avis, accessibilité, sigles, Pactols (avec un faux serveur), réglages, candidats, relecture, annuaire, conversions du navigateur, disque… Chaque vérification est une ligne `na_verifier( condition, 'ce qui doit être vrai', $obtenu )`. Un essai calcule, nettoie ce qu'il a créé, puis vérifie.

Pour l'exécuter hors de l'intégration continue : un WordPress avec WP-CLI, l'extension installée et activée, puis `wp eval-file .github/essais/file-des-termes.php`. WordPress Playground (`@wp-playground/cli`) convient aussi, en montant le dossier de l'extension.

sDrive s'éprouve sans sDrive : `.github/essais/faux-sdrive.php` est un petit serveur WebDAV qui répond comme lui, et sait aussi tomber en panne (507 pour un compte plein, 401 pour un mot de passe refusé). Les scripts `ranger` et `exporter` lancent le rangement et l'export en plusieurs passages contre lui ; `rangement-verification.php` et `export-verification.php` vérifient ensuite ce qui a été écrit, et que rien n'a été effacé du serveur avant d'être relu chez sDrive.

**Toute correction de comportement s'accompagne de son essai**, et un essai doit échouer avant la correction.

### Intégration continue (`.github/workflows/publier.yml`)

À chaque poussée : `analyser` (analyseur PHP 7.2 et 8.4, doublons, commentaires) puis `essayer` (matrice PHP 7.2 / WordPress 6.9, PHP 8.1, 8.3 et 8.4 / dernière version : installation, activation, affichage du formulaire, essais, désactivation, journal sans erreur fatale). Sur `main`, `charger` éprouve la concurrence (section 14), puis, contre le faux sDrive, le rangement et l'export en trois passages, les pannes, le sélecteur de dossier et le dépôt des Word de relecture. Sur une étiquette `vX.Y` : `publier` vérifie que l'étiquette égale la version de l'en-tête, fabrique l'archive et publie la release avec `notice-archeomed.zip`.

### Publier une version

1. Monter la version dans l'en-tête de `notice-archeomed.php` et dans le titre du `README.md`.
2. Committer, étiqueter `vX.Y`, pousser la branche et l'étiquette.
3. Les sites qui ont activé les mises à jour par GitHub (onglet Mises à jour) la voient sous six heures, ou aussitôt après « Vérifier maintenant ».
4. Installer d'abord sur un site d'essai, jamais directement en production.

### Site d'essai

Sur un site dont l'environnement est `local` ou `development` (`WP_ENVIRONMENT_TYPE`), la constante `NA_PROTECTION` à `aucune` suspend la vérification anti-robot pour les essais ; un bandeau le rappelle. Ce réglage est ignoré partout ailleurs.

## 13. Conventions

- **Langue** : noms de fonctions, variables et commentaires en français ; les fonctions héritées en anglais (`handle_submission`, `render_form`…) gardent leur nom.
- **Commentaires** : un bloc par fonction, juste au-dessus d'elle, qui dit ce qu'elle fait et pourquoi ; un bloc détaché de sa fonction est refusé par `verifier-les-commentaires`.
- **Style** : celui de WordPress (tabulations, espaces dans les parenthèses, `array()`), syntaxe compatible PHP 7.2 (ni `match`, ni `?->`, ni arguments nommés, ni `str_contains`). Dans une fonction anonyme hors classe, jamais `self::`.
- **Styles Métopes** : toujours par `Notice_Archeomed_Styles::de()`, jamais en dur.
- **Normes** : toujours par `Notice_Archeomed_Normes::valeur()` ; un nouveau choix éditorial s'ajoute au catalogue, avec le choix actuel par défaut.
- **Avis** : bloquants seulement ceux des appels de figure et du texte alternatif qui ne décrit rien, et seulement au navigateur, avec une case de confirmation (`avisBloquants()`) ; tous les autres sont des conseils. Le serveur ne refuse jamais sur un avis. Même phrase au serveur et au navigateur.
- **Règles acquises** : une notice ne se perd pas parce qu'un courriel échoue ou qu'une métadonnée manque ; deux dépôts simultanés ne se gênent pas ; la prise d'une notice, point où deux processus se disputent, est une écriture conditionnelle ; les compteurs de quotas (transients) ne le sont pas, et sous-comptent au pire sans conséquence.

## 14. Points d'attention

- **Hébergement derrière un proxy** : Turnstile (Cloudflare), la lecture de Pactols et sDrive demandent une sortie HTTPS ; à défaut, protection locale, `pactols_serveur` = `non`, et les fichiers restent sur le serveur. L'onglet Hébergement essaie chacune de ces sorties.
- **Limites de PHP** : `upload_max_filesize` et `post_max_size` bornent les fichiers ; une requête plus lourde que `post_max_size` arrive vide, ce que `refuser_l_envoi_trop_lourd()` détecte et dit à l'auteur.
- **Planificateur** : sans WP-Cron ni tâche système, rien ne part en différé, et rien ne se range sur sDrive.
- **Temps d'exécution** : le rangement et l'export partagent un budget de soixante secondes compté depuis le début de la requête (`Notice_Archeomed_Rangement::echeance()`), parce que le planificateur peut les lancer l'un après l'autre dans la même requête ; chacun garde une marge avant de commencer un fichier (25 s pour le rangement, 30 s pour une figure de l'export). Un passage coupé par le serveur compte comme un essai manqué.
- **Concurrence** : éprouvée à chaque envoi sur `main` par le travail `charger` de l'intégration continue : un WordPress sur MariaDB, servi par huit processus PHP, reçoit vingt-cinq dépôts de deux figures à la même seconde, puis quatre passages du planificateur se disputent les notices ; il doit rester vingt-cinq notices, chacune sa référence, toutes expédiées une seule fois (`.github/essais/charge.php` et `charge-verification.php`). Puis les vingt-cinq sont corrigées par leur lien à la même seconde, cinq d'entre elles deux fois depuis deux pages ouvertes sur la même version : une seule des deux doit passer, et chaque notice être récrite en place, une version de plus, et repartir une seule fois (`corrections.php` et `corrections-verification.php`). Ce n'est pas une mesure de charge de la production : les environnements en SQLite (Playground) ne reproduisent pas les écritures simultanées, et la vérification anti-robot est suspendue pour l'essai.
- **Évolutions de Métopes** : remplacer le gabarit, lancer `verifier-les-styles`, ajuster la correspondance dans l'onglet Styles Métopes plutôt que le code.
- **Courriel** : le serveur de la revue refuse au-delà d'une dizaine de méga-octets ; l'essai de poids de l'onglet Courriel mesure la limite réelle. Les aperçus posés dans le corps comptent dans ce poids comme des pièces jointes ; les originaux, eux, ne voyagent plus.

## 15. Exploitation

Ce qu'un hébergeur ou une équipe qui reprend le plugin doit savoir. Le détail technique est dans les sections précédentes.

### Prérequis

- **PHP 7.2 au moins** (testé en 7.2, 8.1, 8.3 et 8.4) ; extensions `zip` (le document Word) et `imagick` (les versions allégées des figures ; à son défaut, ou s'il échoue, GD réduit les JPEG et les PNG, mais ni les TIFF ni les PDF). Aucune limite de temps ni de mémoire n'est posée à Imagick : elles valent pour le processus entier, non pour une image. GD, lui, déplie l'image entière : `Notice_Archeomed_Paquet::memoire_suffit()` compte cinq octets par pixel et 16 Mo de marge face à la limite relevée pour les images, et l'on renonce à l'aperçu plutôt que de faire tomber le processus. La conversion reçue du navigateur se vérifie alors à sa marque de fin de JPEG.
- **WordPress 5.6 au moins**, base MySQL ou MariaDB.
- **Limites PHP** : `upload_max_filesize` et `post_max_size` à 25 Mo au moins, et au-delà de 40 Mo pour qu'une notice atteigne son plafond : le plafond réel est le plus petit de `MAX_TOTAL_FILESIZE` et de ceux de PHP (`plafonds_des_fichiers()`) ; `max_execution_time` de 60 s au moins, 120 s de préférence.
- **Disque** : une notice arrive avec quarante Mo d'illustrations au plus (`MAX_TOTAL_FILESIZE`, vingt avant la 3.81) ; sans sDrive, elles restent sur le site, et trois cents notices dépasseraient le plafond. L'onglet Hébergement compte le dossier des notices et les téléversements du site, face à un plafond de 2 Go (filtre `notice_archeomed_plafond_disque`, `0` pour ne pas juger) ; la mesure est gardée quinze minutes. Le dépôt ne parcourt pas le disque : il lit l'estimation (`disque_estime()` : dernière mesure plus `na_disque_recu`) et, au-delà de 85 % (`PART_SANS_FICHIERS`) ou de 500 Mo reçus dans l'heure, reçoit la notice sans ses fichiers (`garder_le_disque()`, paramètre `notice_sans_fichiers` à la page de fin). Bandeau d'administration dès 60 %. Relié à sDrive, le site n'y garde plus que les aperçus : originaux, JPEG convertis et autorisations sont rangés après l'envoi dans `<dossier>/<numéro>/originaux/<rubrique>/<Commune>/` (la rubrique V partagée en ses sous-rubriques ; `Caen_2` pour une deuxième notice de la même commune), avec le Word de chaque version (`Commune_Nom_vN.docx`), et effacés d'ici une fois leur copie vérifiée. Le dossier d'une notice est fixé au premier rangement : changer sa commune ensuite ne le déplace pas.
- **Planificateur** : WP-Cron actif (`DISABLE_WP_CRON` absent ou faux), ou une tâche système qui appelle `wp-cron.php` chaque minute. Sans lui, les courriels ne partent pas.
- **Sortie HTTPS** : vers `challenges.cloudflare.com` (Turnstile, selon la protection choisie) ; la recherche Pactols se fait depuis le navigateur du visiteur, mais la résolution des termes à l'expédition (forme préférée, concept déprécié, chaîne ascendante) part du serveur, vers `pactols.frantiq.fr` ; à son défaut elle se retente, et la notice part sans. Vers sDrive si les illustrations doivent y être rangées : l'essai de l'onglet Hébergement dit si le proxy de l'hébergement laisse passer. Le site s'y relie depuis le même onglet, sans mot de passe tapé : l'administrateur autorise le site dans sDrive, qui lui rend un mot de passe d'application révocable, scellé dans la base avec les clés de `wp-config.php`. Le site demande aussi à sDrive l'identifiant interne du compte (`/ocs/v2.php/cloud/user`) : c'est lui qui nomme le dossier WebDAV quand la connexion passe par la fédération d'identité sous un autre nom ; à son défaut, le nom de connexion sert.

### Réglages posés dans `wp-config.php`

| Constante | Effet |
|---|---|
| `NA_TURNSTILE_SECRET` | clé secrète Turnstile |
| `NA_PROTECTION` | `aucune` suspend la vérification, **sur un site déclaré `local` ou `development` seulement** |
| `NA_PROXYS` | relais de confiance (adresses ou blocs CIDR, séparés par des virgules), derrière lesquels l'adresse du visiteur se lit dans `X-Forwarded-For` ; sans elle, `REMOTE_ADDR` seul |
| `NA_SDRIVE_WEBDAV`, `NA_SDRIVE_IDENTIFIANT`, `NA_SDRIVE_MOT_DE_PASSE`, `NA_SDRIVE_DOSSIER` | l'accès WebDAV à sDrive (mot de passe d'application, jamais celui de Janus), toutes quatre ou aucune ; elles priment sur la liaison faite dans l'onglet Hébergement, inutile quand elles manquent |
| `NA_SUPPRIMER_LES_NOTICES` | à `true` avant de supprimer l'extension : efface aussi les notices et leurs fichiers (sinon ils restent) |

Le filtre `notice_archeomed_turnstile_ouvert` (à `__return_false`) ferme la vérification quand Cloudflare est injoignable ; par défaut elle laisse passer, pour ne pas perdre de notice derrière un proxy qui le bloque. La pièce de puzzle s'y ajoute alors (transient `na_turnstile_injoignable`) le temps que la sortie revienne ; une page affichée avant la panne, sans puzzle, est encore admise pendant un jour.

### Cache et réseau

- La page du formulaire porte un jeton, une heure d'affichage et un défi propres à chaque affichage : le plugin envoie `DONOTCACHEPAGE` et les en-têtes de non-cache. Un cache de page qui les ignore doit exclure l'adresse du formulaire.
- Le jeton du formulaire vit de 24 à 48 heures (le défaut de WordPress est de 12 à 24), pour ce seul formulaire.
- **Sous nginx**, le `.htaccess` du dossier `uploads/notice-archeomed/` est sans effet : seul le nom aléatoire de 32 caractères protège les fichiers. Ajouter `location ~ ^/wp-content/uploads/notice-archeomed/ { deny all; }`.

### Sauvegarde et restauration

À sauvegarder : **la base de données** (les notices et leurs métadonnées y sont) **et le dossier `wp-content/uploads/notice-archeomed/`** (figures, autorisations, aperçus, conversions). Les illustrations vont jusqu'à 40 Mo par notice : sans sDrive, prévoir plusieurs Go par an. Relié à sDrive, le site n'y garde que les aperçus ; les originaux et les Word sont sur sDrive, qui a ses propres sauvegardes, et la table `_na_sdrive` de chaque notice dit où ils sont. Restaurer les deux ensemble ; une notice dont le fichier manque s'affiche « fichier introuvable sur le serveur » dans sa fiche.

### Supervision

- Le bandeau de l'administration signale les notices en échec ou en attente depuis plus de 30 minutes ; il ne se voit que si quelqu'un ouvre l'administration. Une sonde externe peut interroger la liste des notices en état `en_attente` ou `echec`.
- Les incidents s'écrivent dans le journal PHP, préfixe `Notice Archeomed:`.
- L'onglet Hébergement montre les vingt derniers envois refusés, avec leur cause (jeton refusé, champ piège rempli, heure d'affichage absente ou forgée, plafond atteint, protection, champs manquants…), l'âge de la page et si l'auteur était connecté : c'est là qu'on comprend la plainte d'un auteur dont le dépôt « ne passe pas ». Il dit aussi où en sont le rangement et l'export sur sDrive, et ce qui les bloque.
- Plafonds : 300 tentatives et 15 envois par heure et par adresse (filtre `notice_archeomed_plafond_par_poste`), 10 saisies gardées après refus par heure et par adresse, 100 dépôts et 500 Mo de fichiers par heure toutes adresses confondues. Les compteurs sont rangés sous l'heure en cours (`cle_horaire()`). Un refus « quota » à répétition indique un robot, ou un relais non déclaré dans `NA_PROXYS`.

### Données personnelles

L'outil d'exportation de WordPress rend les notices où figure une adresse de courriel ; celui d'effacement n'efface rien et dit à la rédaction de décider (une notice est une archive éditoriale). Un texte est proposé à la page de politique de confidentialité du site ; **le responsable du traitement, la base légale et la durée de conservation sont à écrire par la revue**. Le brouillon gardé dans le navigateur de l'auteur s'efface au bout de 30 jours.

### Désinstallation

Supprimer l'extension efface les réglages et leurs secrets, les attentes en base, et les deux rôles de la relecture avec la capacité donnée aux administrateurs (un compte qui n'avait que ce rôle reste, sans rôle) ; les notices et les fichiers restent, sauf `NA_SUPPRIMER_LES_NOTICES`. L'état, le journal et les versions de relecture sont des métadonnées des notices : ils restent ou partent avec elles.

### Imagick

Avec l'extension `imagick`, le serveur lit le TIFF et le PDF et en tire lui-même les aperçus et les deux définitions du dossier Métopes. Sans elle, il ne lit ni l'un ni l'autre : le **TIFF** se convertit dans le navigateur de l'auteur (UTIF.js) en deux JPEG, la haute définition et la basse, aux mesures des réglages (10 × 15 cm à 300 ppp, puis 2 500 px au plus et 1 Mo environ, par défaut) ; le **PDF** (pdf.js, chargé à la demande, `assets/pdfjs/`) donne les deux mêmes JPEG, tirés de sa première page, et le PDF d'origine reste dans `icono/originaux`. Le serveur range les JPEG à demeure (`_na_conversions`) et en tire le dossier Métopes, le Word, la relecture et l'aperçu du courriel. `reglages_de_conversion()` dit au navigateur s'il doit convertir, et à quelles mesures.

Avec ou sans Imagick, la **vignette** d'un TIFF ou d'un PDF sur la carte de la figure est dessinée par le navigateur, pour l'écran seulement ; avec Imagick, rien de cette vignette ne part. De même, un **TIFF trop lourd** se passe en JPEG dans le navigateur, d'un bouton, à sa pleine définition (qualité 0,92), puis par paliers jusqu'à la norme des photographies au plus bas ; au-delà de 36 millions de pixels, il est refusé.

Le TIFF de 8 ou 16 bits par couche, en niveaux de gris, en RVB ou en CMYK, se lit par `rgbaDuTiff()` plutôt que par UTIF, qui ne lit le CMYK qu'en 8 bits : un CMYK de 16 bits, tel que Photoshop l'enregistre pour l'impression, sortait rayé et dédoublé. Un TIFF qui porte un profil ICC (étiquette 34675) passe au sRGB par Little CMS (lcms-wasm, `assets/lcms/`, chargé à la demande par `chargerLcms()`), en intention colorimétrique relative avec compensation du point noir (`preparerLesCouleurs()`) ; un RVB déjà sRGB n'est pas touché, et un CMYK sans profil lisible passe par la formule de pdf.js. Une couche de transparence se pose sur du blanc. Un TIFF aux couches rangées l'une après l'autre (`PlanarConfiguration` = 2) est refusé comme illisible. Un fichier mal lu ou trop lourd est refusé : l'auteur enregistre la figure en JPEG.

Sur le serveur, `vers_le_srgb()` ramène au sRGB, avant les aperçus, une image en CMYK ou en RVB qui porte un autre profil que le sRGB, par le profil du plugin (`assets/srgb.icc`, écrit par `.github/essais/profil-icc`) et en intention perceptive ; un CMYK sans profil passe par la conversion d'ImageMagick. Il faut un ImageMagick construit avec Little CMS : l'onglet Hébergement le vérifie, et le travail `couleurs` de l'intégration continue l'éprouve sur un vrai Imagick.

Un **PDF** se dessine avant de se réduire, à une résolution choisie d'après la plus grande page qu'il déclare (`/MediaBox`, lue par `plus_grande_page_pdf()`) : pour l'écran, juste assez pour la mesure voulue (150 ppp au plus) ; pour la haute définition, à la densité réglée (600 ppp au plus). Dans les deux cas, la page dessinée ne passe pas `MEGAPIXELS_PDF` (36 millions de pixels, une A4 à 600 ppp) : depuis la 3.91, une planche qui se déclarait de 87 × 62 cm — la taille en pixels d'une A4 inscrite comme une taille en points — ne fait plus couper le serveur. Une haute définition ainsi bridée est notée au lisez-moi. Si la page lue se révèle plus petite que la plus grande déclarée, elle se relit à sa vraie mesure.

`Notice_Archeomed_Paquet::imagick_disponible()` est le seul test d'Imagick ; la constante `NA_SANS_IMAGICK` le fait échouer sur un site d'essai. Un bandeau d'administration le signale, et l'onglet Hébergement donne l'état du serveur (Imagick, GD, formats acceptés). Une mise à jour de PHP chez l'hébergeur peut retirer l'extension sans prévenir ; à redemander alors : l'installation de `php-imagick`, et, pour les PDF, Ghostscript autorisé par la politique d'ImageMagick.

### Mise à jour

Par dépôt manuel de `notice-archeomed.zip` (produit par `./empaqueter`, ou téléchargé depuis la version publiée sur GitHub). La mise à jour automatique depuis GitHub est inactive par défaut ; l'archive n'est pas signée, la confiance repose sur le compte GitHub du dépôt.

## 16. La relecture sur le site

### Ouverture et droits

L'option `notice_archeomed_relecture` porte `close` : tant qu'elle est fausse, `droit_sur()` rend null pour tout le monde — direction comprise, seul un regard (`Notice_Archeomed_Regard`, ci-dessous) y échappe — et les liens de correction des auteurs vivent ; vraie, la relecture s'ouvre et `contexte_valide()` refuse toute correction d'auteur (`par_la_redaction` = 0) — le formulaire affiche « La campagne est close », et un envoi déjà ouvert est refusé avec la raison `close`. La correction par la direction (`peut_tout()`) depuis la fiche ou le courriel (« Corriger dans le formulaire ») reste ouverte, sauf pour une notice validée.

Deux rôles, vérifiés à chaque chargement (`assurer_les_roles()`, parce qu'une mise à jour par archive ne repasse pas par l'activation) : `na_coordinateur` (`read`, `na_relire`) et `na_direction` (`read`, `na_relire`, `na_relire_tout`, `na_regler`). L'administrateur reçoit les deux capacités de relecture ; `na_regler` ne s'écrit pas dans son rôle, le filtre `user_has_cap` (`aux_administrateurs()`) la donne à qui a `manage_options`. Les comptes de l'ancien rôle `na_redaction` passent à `na_direction`, puis le rôle est retiré.

`na_regler` (`Notice_Archeomed_Settings::CAPACITE`) ouvre l'extension entière : c'est la capacité du type `na_notice` (liste, fiche, corbeille) et du sous-menu des réglages, et `peut_regler()` remplace `manage_options` dans chaque contrôle — onglets, téléchargements, relances, export, bandeaux —, le filtre `option_page_capability_{groupe}` le disant à `options.php`. La « direction seule » (`est_direction_seule()` : `na_regler` sans `manage_options` ni `edit_posts`) n'entre que dans la Chronique et dans « Relecture » : `garder_la_direction()`, à `auth_redirect`, laisse passer `edit.php?post_type=na_notice` (et les réglages qui s'y rangent), `post.php` pour une notice (`post` ou `post_ID`), `options.php` et la page « Relecture », et renvoie de toute autre page à la liste des notices (`renvoi_de_la_direction()`) ; l'adresse `admin.php?page=…` des réglages de la 3.85 mène à la nouvelle, avec son onglet ; `admin-post.php` et `admin-ajax.php` n'y passent pas, chaque geste y contrôle son droit. Le tableau de bord et le profil sont ôtés de son menu ; `login_redirect` la mène à la page de relecture. Le lien de correction du courriel (`post.php?…&na_corriger=1`) est servi sur le même crochet, à la priorité 5, avant la garde.

`droit_sur( $id, $compte )` est le seul juge, pour l'affichage comme pour chaque écriture, et ne lit que la base : `tout` (direction), `principale` (coordinateur de `_na_rubrique`, pour une notice que le fascicule porte), `renvoi` (coordinateur d'une rubrique que `renvoi_1` ou `renvoi_2` désigne), ou null. `peut_corriger()` y ajoute l'état : le coordinateur corrige en `a_relire`, `relue` et `a_soumettre` ; personne ne corrige une notice `validee`.

Pour `tout`, le sommaire est celui du fascicule (`afficher_le_sommaire_de_tout()`) : un `<details>` par rubrique, ouvert pour celle de la notice, avec le compte des notices validées. La validation est le bouton `suite=valider`, non la liste des états, qui ne la propose pas : `enregistrer()` pose `validee`, garde l'état d'avant dans `_na_relecture_avant_validation` et rend le verrou ; « Suivante » et « Valider » vont à la notice suivante par `ordre_de_lecture()`, à travers les rubriques pour `tout`. Sur une notice validée, tout POST qui changerait la saisie, un renvoi ou l'état rend l'issue `figee` ; `contexte_valide()` refuse de rouvrir le formulaire, et `ouvrir_dans_le_formulaire()` s'arrête. `rouvrir_la_notice( $id, $compte, $motif )` (action `admin-post` `na_relecture_rouvrir`, avec jeton) est réservé à `tout` : l'état d'avant revient, et le journal note « rouverte après validation » avec le motif en remarque.

### La page publique

L'écran est le shortcode `[relecture_archeomed]` (`afficher()`), sur une page que `page()` retrouve comme `page_du_formulaire()` retrouve celle du dépôt. Comme pour le formulaire, les scripts sortent du contenu et partent au pied de page (priorité 25, après Quill, chargé par `charger_l_editeur()` sur cette seule page) ; les styles (`poser_les_styles()`) sont cloisonnés sous `.na-rel` et ne comptent sur aucune classe de l'administration. `garder_la_page()`, à `template_redirect`, envoie `nocache_headers()` et renvoie à `wp_login_url()` qui n'est pas connecté. Les adresses portent `na_rubrique` (le rang) et `na_notice`.

Un « coordinateur seul » (`est_coordinateur_seul()` : `na_relire`, sans `na_relire_tout` ni `edit_posts`) n'a pas de barre d'administration (`show_admin_bar`), arrive sur la page après sa connexion (`login_redirect`) et y est renvoyé de toute page de `wp-admin` (`admin_init`, `renvoi_de_l_administration()`), sauf `admin-ajax.php` (le verrou) et `admin-post.php` (enregistrement, aperçus, lecture seule, Word). Les gestes restent des actions `admin-post`, chacune avec son jeton et son `droit_sur()`. Le menu « Relecture » de l'administration donne l'adresse de la page et, pour `peut_tout()`, le choix d'un regard.

### Enregistrer

`Notice_Archeomed_Relecture::enregistrer()` vérifie le droit, prend le verrou, appelle `Notice_Archeomed_Pactols::enregistrer_la_relecture()`, puis pose l'état, la remarque et la ligne du journal, et inscrit le Word au dépôt. `enregistrer_la_relecture()` suit la correction en place : `saisie_relue()` part de la saisie gardée et n'y change que les champs postés (reçus par les fonctions du dépôt : `textes_postes()`, `collect_lieux()`, `collect_pactols_keywords()`, `texte_notice_pose()`, `textes_de_figure()`), tout refus précède toute écriture, et la version passe par `changer_de_version()`. Rien de changé : pas de version. La notice ne repart pas vers la rédaction et personne n'est prévenu.

Règles tenues par le serveur, quoi que poste la requête : aucun courriel de responsable n'est lu ; aucun fichier n'est lu, aucune figure ajoutée ni remplacée ; une figure retirée exige un motif de `MOTIFS` ; un terme neuf du thésaurus exige un ARK de Frantiq ; un lieu du titre inchangé garde son ARK, un lieu changé prend celui que la recherche du navigateur a posé (ARK de Frantiq, ou rien) ; la rubrique principale ne change que pour `tout` ; un renvoi posé ne vise ni la rubrique de la notice ni une rubrique déjà visée ; la portée `renvoi` ne retire que le renvoi vers sa rubrique ; `soumettre_la_notice()` refuse toute notice qui n'est pas « à soumettre ».

### Verrou

`_na_relecture_verrou` vaut « heure:compte ». `prendre_le_verrou()` est une écriture conditionnelle (`UPDATE … WHERE` libre, périmé ou déjà le nôtre), sur le modèle de `prendre()` ; la page le renouvelle chaque minute (`wp_ajax_na_relecture_verrou`) et le rend en partant (`na_relecture_liberer`, par `sendBeacon`). **Ne pas le remplacer par `wp_set_post_lock`**, qui lit puis écrit.

### Word à suivi de modifications

`word_de_relecture( $id, $version )` compose deux documents par `remplir_le_document()` — la version et celle d'avant, toutes deux passées par `preparer_la_saisie()` — puis `Notice_Archeomed_DOCX::suivre_les_modifications()` les rapproche : alignement des paragraphes (plus longue sous-suite commune, début et fin communs écartés d'abord), puis des mots dans les paragraphes de même style qui se répondent. Un paragraphe entier porte la révision sur sa marque de fin (`w:rPr` de `w:pPr`) ; un texte supprimé sort en `w:delText`, hors de tout lien. La feuille de styles n'est pas touchée. Le fascicule et le dossier Métopes ne passent jamais par cette fonction.

Le Word se refait à volonté des versions gardées : aucun n'est conservé sur le site, même quand sDrive tarde à le prendre. La tâche `na_deposer_les_relectures` le dépose dans `Notice_Archeomed_Rangement::dossier_de( $id )`, sous `nom_du_word()`.

### Voir comme un coordinateur

L'entrée « Relecture » (`afficher_le_lien()`, puis `afficher_le_choix_du_regard()`) propose, à qui a `peut_tout()`, les valeurs de `regards_possibles()` : `compte:<id>` pour chaque compte que les réglages affectent à une rubrique, `rubrique:<rang>` pour chaque rubrique. Le bouton poste `admin-post.php?action=na_relecture_vue` (jeton `na_relecture_vue`) ; `choisir_la_vue()` exige `peut_tout()` du compte réel, et `emprunter_le_regard()` garde le choix dans le transient `na_relecture_vue_<compte>` pour `DUREE_DE_LA_VUE` (deux heures). La rubrique s'y garde par son nom, non par son rang. « Revenir à ma vue » est `na_relecture_vue_quitter` (jeton du même nom) : permis à tout compte connecté, il n'efface que son propre choix.

`vue()` relit ce choix à chaque requête et rend le regard, ou null : si le compte réel n'a plus `peut_tout()`, ou si `regard_choisi()` ne le permet plus (compte qui ne relit pas, qui relit tout, sans rubrique dans les réglages ; rubrique sortie du fascicule), le transient s'efface. Seuls l'écran et les gestes de relecture l'appellent : ni l'administration, ni `show_admin_bar`, ni `login_redirect`, ni `garder_la_direction()`.

Le regard est un `Notice_Archeomed_Regard`, fille de `WP_User`, que `droit_sur()`, `rubriques_de()`, `peut_tout()` et `peut_corriger()` reçoivent comme un autre compte. Celui d'un compte (`du_compte()`) est construit depuis le compte et porte ses capacités telles que la base les donne. Celui d'une rubrique (`de_la_rubrique()`) porte l'identifiant -1, que WordPress ne rattache à personne, le rôle `na_coordinateur` et `rubriques_fixees` ; ses champs de compte sont posés d'avance pour que `__get()` n'aille pas lire les métadonnées d'un compte réel. `droit_sur()` tient la campagne pour close devant un regard, et devant lui seul.

L'écran (`afficher()`) dessine tout avec les droits du regard (`vu_par()`), pose le bandeau (`afficher_le_bandeau()`), lit le verrou sans le prendre (`verrou_de()` : la notice est tenue si personne ne la tient ou si c'est le coordinateur), puis `neutraliser()` ajoute `disabled` à chaque `input`, `select`, `textarea` et `button` du HTML rendu, sauf l'agrandissement des figures ; les scripts n'y sont pas touchés. Le script reçoit `vue` : Quill s'ouvre en `readOnly`, la barre et les × des mots-clés sont désactivés, l'envoi du formulaire est empêché, et le verrou n'est ni renouvelé ni rendu.

Côté serveur, rien ne dépend de ces désactivations : `enregistrer()` rend `interdit` à un regard ; `noter_au_journal()` n'écrit rien pour lui ; `soumettre_la_notice()` le refuse ; `recevoir()`, `soumettre()` et `recevoir_la_reouverture()` refusent tout tant que `vue()` n'est pas null, après le jeton et avant toute écriture, avec un message ; `tenir_le_verrou()` refuse aussi, de sorte qu'un onglet ouvert sous son propre compte perd son verrou pendant la vue. `rendre_le_verrou()` reste permis : il ne rend que le verrou du compte réel. Les lectures — `rendre_l_apercu()`, `rendre_le_word()` (sans les adresses des responsables quand la sorte n'est pas `tout`), `rendre_la_lecture()` — passent par `droit_sur( $id, regard() )` : ce que le coordinateur obtiendrait, ni plus ni moins.

### Essais

`file-des-termes.php` éprouve : la fermeture des liens d'auteur, le cloisonnement (identifiant forgé compris), l'ordre du fascicule, le verrou et son expiration, le refus de modifier un courriel, d'ajouter ou de remplacer une figure, le masquage avec motif et le rétablissement, les mots-clés, les versions, les états et le journal, l'ajout et les deux retraits de renvoi, la soumission à l'auteur (courriel refusé puis parti), la présence de `w:ins` et `w:del` dans le Word de relecture et leur absence du fascicule, l'absence des courriels dans la page et le Word d'un coordinateur, le rendu du shortcode selon le compte, le renvoi hors de l'administration, les lieux par le thésaurus, le lien de la direction avant et après la clôture, le passage de l'ancien rôle à la direction et ses droits, les pages de l'administration où elle entre, la validation par le seul bouton, la notice validée figée (écran et lien du courriel), la réouverture refusée au coordinateur et notée au journal avec son motif, la vue d'un coordinateur (droits du regard égaux à ceux du coordinateur, regard d'une rubrique sans compte, choix refusé à un coordinateur et propre à chaque compte, aucune écriture, aucun journal, aucun verrou, aucun envoi, bandeau et champs désactivés, campagne tenue pour close pour le seul regard, regard effacé quand les réglages ne le permettent plus). Le travail `essayer` ouvre la page par HTTP — sans connexion, puis en coordinateur, `wp-admin` compris ; le travail `charger` dépose un Word de relecture sur le faux sDrive.
