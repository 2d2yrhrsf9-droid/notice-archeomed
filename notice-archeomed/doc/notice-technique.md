# Notice technique

Ce document s'adresse à qui doit reprendre l'extension : la corriger, la faire évoluer, l'adapter à une autre revue. Il décrit son architecture, ses données, ses tâches planifiées, ses réglages, la fabrication des documents, ses essais et sa publication. Le fonctionnement vu de l'utilisateur est décrit dans la présentation, l'autre document de cet onglet ; le `README.md` du dossier détaille les choix éditoriaux un à un.

Le code est commenté en français, en phrases : chaque fonction dit ce qu'elle fait et, le plus souvent, pourquoi elle a pris sa forme actuelle. Ces commentaires sont la première documentation ; ce qui suit en est la carte.

## 1. En bref

| | |
|---|---|
| Nature | Extension WordPress, sans dépendance installée par Composer ni par npm |
| PHP | 7.2 au moins (essayé en 7.2, 7.4, 8.1 et 8.3) |
| WordPress | 5.6 au moins (essayé en 6.9 et en dernière version) |
| Extensions PHP | `zip` (DOCX et dossiers ; sans elle, repli en RTF), `imagick` (versions allégées et conversions ; sans elle, les originaux voyagent), `mbstring` conseillée |
| Bibliothèques chargées par le navigateur | Quill 2.0.3 (éditeur du texte, jsDelivr, avec empreinte SRI), Turnstile de Cloudflare (selon la protection choisie), axe-core 4.10.2 (onglet Accessibilité seulement, cdnjs, avec empreinte SRI) |
| Services joints par le serveur | Pactols (`pactols.frantiq.fr`, lecture des termes, coupable dans les réglages), Cloudflare (vérification Turnstile, si choisie), GitHub (mises à jour), le serveur de courriel |
| Shortcode | `[notice_archeomed_pactols]` |
| Domaine de traduction | `notice-archeomed` (les textes sont écrits en français dans le code) |

Le principe directeur : **accepter vite, expédier ensuite**. Le dépôt d'un auteur est inscrit en base en une fraction de seconde ; tout ce qui est lent — versions allégées, document Word, courriels, lecture du thésaurus — se fait après, sous le planificateur de WordPress. Deux dépôts simultanés ne doivent jamais se gêner, et une notice ne doit jamais se perdre parce qu'un courriel échoue ou qu'une métadonnée manque.

## 2. Les fichiers

```
notice-archeomed/                     le dossier de l'extension, tel qu'il est installé
  notice-archeomed.php                classe principale Notice_Archeomed_Pactols (≈ 10 800 lignes)
  class-notice-archeomed-file.php     la file d'attente et l'administration des notices reçues
  class-notice-archeomed-settings.php la page de réglages et ses onglets
  class-notice-archeomed-docx.php     le générateur de DOCX à partir du gabarit Métopes
  class-notice-archeomed-rtf.php      le générateur RTF, repli sans ZipArchive
  class-notice-archeomed-paquet.php   le dossier Métopes d'une rubrique (zip) et les images
  class-notice-archeomed-thesaurus.php la lecture des concepts Pactols et les blocs d'index TEI
  class-notice-archeomed-typographie.php la typographie française du texte
  class-notice-archeomed-controles.php les avis donnés au dépôt, communs au serveur et au navigateur
  class-notice-archeomed-normes.php   les normes éditoriales, en réglages
  class-notice-archeomed-styles.php   la correspondance des blocs avec les styles Métopes, en réglages
  class-notice-archeomed-accessibilite.php l'onglet Accessibilité et l'encart des figures sur la fiche
  class-notice-archeomed-candidats.php l'onglet Candidats à Pactols
  class-notice-archeomed-documentation.php l'onglet Documentation, qui affiche ces pages
  class-notice-archeomed-nommage.php  la règle des noms de fichiers
  class-notice-archeomed-maj.php      les mises à jour depuis GitHub
  modele-metopes.docx                 le gabarit Métopes livré (feuille de styles de référence)
  modele-metopes.rtf                  son pendant RTF
  assets/                             icônes de l'extension
  doc/                                la présentation et cette notice
  README.md                           les choix éditoriaux et fonctionnels, détaillés
.github/workflows/publier.yml         intégration continue et publication des versions
.github/essais/                       les essais de non-régression
verifier-la-syntaxe                   filet de syntaxe PHP sans PHP (Python)
verifier-les-styles                   confronte les styles du code au stylage de référence
empaqueter                            fabrique l'archive à téléverser
```

Le dépôt Git contient le dossier de l'extension et les outils qui l'entourent ; seul `notice-archeomed/` part dans l'archive installée.

L'ordre des `require_once`, en tête de `notice-archeomed.php`, compte : les classes utilitaires (nommage, paquet, mises à jour, RTF, typographie, contrôles, normes, styles, DOCX) avant les réglages et la file, qui les emploient. Viennent ensuite les instanciations : `Notice_Archeomed_Settings`, `Notice_Archeomed_Accessibilite`, `Notice_Archeomed_Candidats`, `Notice_Archeomed_Documentation`, puis la file en **globale** `$notice_archeomed_file` (indispensable sous WP-CLI, qui charge WordPress dans une fonction : sans `global`, une seconde file naissait et doublait les crochets), puis le module de mises à jour (`$notice_archeomed_maj`), et, en fin de fichier, la classe principale en globale `$notice_archeomed_plugin`.

## 3. Les classes et leurs responsabilités

### Notice_Archeomed_Pactols (notice-archeomed.php)

Le nom est historique : la classe porte tout le formulaire, pas seulement Pactols. Ses grands blocs, dans l'ordre du fichier :

- **Constantes** : gabarits, plafonds (`MAX_FILES` = 3, `MAX_TOTAL_FILESIZE` = 20 Mo — le plafond réel est le plus petit de celui-ci et de ceux de PHP, voir `plafonds_des_fichiers()`), quotas (`MAX_ATTEMPTS_PER_HOUR`, `MAX_SENDS_PER_IP`, `MAX_SENDS_PER_EMAIL`, `MAX_LOOKUPS_PER_HOUR`), extensions admises, constantes du puzzle, empreintes SRI de Quill, identifiants des thésaurus Pactols (`TH_1` pour sujets et périodes, `th17` pour les lieux), rubriques et matières.
- **Listes métier** : `$rubriques` (sept rubriques), les natures d'opération avec leur ARK (`$natures`), les familles d'opérations, les matières de la rubrique V, les anciens libellés de rubrique (`RUBRIQUES_ANCIENNES`, pour relire les notices déposées avant un changement de nom).
- **Protection** : `protection()`, `verifier_la_protection()`, le puzzle (`defi_du_puzzle()`, `verifier_le_puzzle()`, sceau HMAC à l'heure, sans rien stocker), Turnstile (`verify_turnstile()`), et `protection_suspendue()` pour les sites d'essai.
- **Formulaire** : `render_form()` (de la ligne ≈ 2 090 à ≈ 5 960 : HTML, CSS et JavaScript en ligne), `enqueue_assets()`, la reprise d'une saisie (`garder_la_saisie()`, `reprise()`, `repris*()`), les messages de retour (`messages_du_retour()`, `panneau_de_fin()`).
- **Réception** : `handle_submission()` et ses `collect_*()`, `handle_uploads()`, `recevoir_les_autorisations()`, `champs_manquants()`, `entrees_attendues()` (ramène chaque champ à la forme attendue avant toute lecture), `refuser_l_envoi_trop_lourd()`.
- **Fabrication** : `build_notice()` (le HTML du courriel), `remplir_le_document()` (le DOCX ou RTF d'une notice), `fabriquer_le_fascicule()`, `page_de_relecture()`, `indexation_de()` (blocs d'index TEI), la typographie et les siècles en HTML.
- **Expédition** : `expedier_de_la_file()` (appelé par le planificateur), `expedier()`, `pieces_du_courriel()`, SMTP (`acheminer_par_smtp()`), les essais d'envoi et le diagnostic.
- **Thésaurus** : la file de résolution des termes (`resoudre_en_tache()`, `resoudre_les_termes()`, `mettre_en_attente()`…), les formes préférées.
- **Fichiers** : rangement des illustrations et autorisations, versions allégées (aperçus), purge des fichiers temporaires, téléchargements protégés.
- **Téléchargements d'administration** : document d'une notice, fascicule, dossier Métopes, illustration.

### Notice_Archeomed_File (class-notice-archeomed-file.php)

Le type de contenu `na_notice`, la file d'attente et tout l'écran « Notices reçues » : colonnes, tris, vues par état, recherche étendue, actions de ligne et groupées, fiche d'une notice (en-tête, notice, suivi, dépannage, illustrations), bandeaux d'alerte (adresse manquante, échecs, retards), récapitulatif quotidien, liens des fascicules et dossiers par rubrique, renvois entre rubriques, classement du volume, mise à niveau des notices anciennes (`rattraper_les_anciennes()`).

### Les autres classes

- **Notice_Archeomed_Settings** : la page de réglages (sous-menu du type `na_notice`), ses onglets (`onglets()`), l'option `notice_archeomed_options` et son nettoyage (`sanitize()`), les essais de la page (`faire_un_essai()` : Turnstile, envoi, essais successifs, poids, GitHub, Pactols), l'état de l'hébergement (`etat_du_serveur()`). `get()` lit une valeur en donnant la priorité à une constante de `wp-config.php`.
- **Notice_Archeomed_DOCX** : ouvre le gabarit, indexe ses styles (nom affiché ⇒ identifiant), construit `word/document.xml` paragraphe par paragraphe et recopie tout le reste du gabarit. Voir la section 8.
- **Notice_Archeomed_RTF** : même interface, pour un hébergement sans ZipArchive. Le DOCX reste la voie normale.
- **Notice_Archeomed_Paquet** : l'atelier d'un dossier Métopes (`style/`, `XML/`, `icono/hr`, `icono/br`, `icono/originaux`), les deux définitions de chaque image, le lisez-moi, l'archive zip ; la fabrication des aperçus (`apercu_du_document()`).
- **Notice_Archeomed_Thesaurus** : `resoudre()` (un concept, sa chaîne ascendante et ses étiquettes en toutes langues), `index_tei()`, la mise en réserve (transients d'un mois pour un concept lu, d'une heure pour un échec).
- **Notice_Archeomed_Typographie** : la correction typographique française (insécables, apostrophes, ponctuation d'un passage en langue étrangère). Corpus d'essai : `.github/essais/typographie.json`.
- **Notice_Archeomed_Controles** : les avis du dépôt. Chaque contrôle existe en PHP et en JavaScript avec les mêmes phrases et les mêmes seuils ; le serveur envoie au navigateur phrases, motifs et seuils (`regles_d_accessibilite()`), pour qu'ils ne divergent pas.
- **Notice_Archeomed_Normes** et **Notice_Archeomed_Styles** : deux catalogues déclaratifs d'où se construisent les onglets de réglages et que le code lit (section 7).
- **Notice_Archeomed_Accessibilite** : contrôle axe-core lancé depuis le navigateur de l'administrateur, trace de l'audit Ara, encart « Accessibilité des illustrations » sur la fiche avec historique des corrections.
- **Notice_Archeomed_Candidats** : relevé des termes gardés hors de Pactols, statut et note de la rédaction, export CSV.
- **Notice_Archeomed_Documentation** : rend ces fichiers Markdown dans l'onglet Documentation, par un convertisseur volontairement restreint (titres, paragraphes, listes, tableaux, code, liens, gras, italique).
- **Notice_Archeomed_Nommage** : la règle des noms de fichiers (sans accent, sans espace, souligné pour séparateur) et la construction sur le modèle des réglages (`{numero}_{rubrique}_{commune}_{lieu_dit}_{annee}_Fig_{n}`).
- **Notice_Archeomed_MiseAJour** : glisse la dernière release GitHub dans le transient des mises à jour de WordPress.

## 4. Le parcours d'un dépôt

1. **Affichage.** Le shortcode rend le formulaire. `enqueue_assets()` ne charge Quill et la protection que sur une page qui porte le shortcode. Le navigateur garde un brouillon en `localStorage` (clé `na_brouillon_` + chemin de la page), effacé après un dépôt réussi.
2. **Saisie.** Le JavaScript du formulaire : éditeur Quill (gras, italique, exposant, petites capitales ; pas de notes), recherche Pactols directe depuis le navigateur (`/api/autocomplete`, `/openapi/v1/concept`), lieux et précision géographique, figures (glisser-déposer pour réordonner, mesure de la définition JPEG et TIFF lue dans le fichier, texte alternatif obligatoire, description détaillée, autorisation de reproduction), avis en direct, compteur de mots, récapitulatif des erreurs avant envoi.
3. **Envoi.** Un POST classique (multipart) vers la page elle-même. `handle_submission()` est accroché à `init` et ne fait rien sans le champ `notice_archeomed_envoi`.
4. **Contrôles d'entrée**, dans l'ordre : forme des champs (`entrees_attendues()`), jeton `notice_archeomed_submit`, pot de miel `na_website`, délai minimal de trois secondes (`na_ts`), quota de tentatives par adresse IP, protection (puzzle et/ou Turnstile).
5. **Collecte et nettoyage** de chaque champ, bornes de longueur (`limit_string()`, qui note les coupes pour l'auteur), normalisations (année, lieu-dit sans point final, département sans parenthèses), termes Pactols (ARK vérifiés par `Thesaurus::ark_propre()`, un terme sans ARK est marqué `libre`), texte HTML nettoyé (`clean_richtext()`).
6. **Champs obligatoires** (`champs_manquants()`), quota d'envois, téléversements (`handle_uploads()` : extension, type MIME réel, PDF valide, plafonds) et autorisations.
7. **Inscription.** `Notice_Archeomed_File::deposer()` crée le post `na_notice` (privé) et ses métadonnées, pose la marque `_na_preparation`, puis l'état `en_attente` — dans cet ordre, pour qu'aucune relance ne la prenne pendant sa préparation. La référence est tirée au hasard (six caractères).
8. **Préparation**, dans la requête de l'auteur : les illustrations sont rangées à demeure (`archiver_les_illustrations()`), les autorisations aussi, les versions allégées fabriquées si Imagick est là, le Word fabriqué (`fabriquer_le_document()`), puis la marque `_na_preparation` est ôtée. Si la requête meurt, la marque vieillit (`ABANDON` = 600 s) et la file reprend la notice.
9. **Retour à l'auteur.** En envoi différé (le défaut), `programmer()` réserve l'expédition et l'auteur est redirigé vers le panneau de fin, avec sa référence. En envoi immédiat, l'expédition a lieu dans la requête, protégée par un `try/catch` : une erreur est consignée, jamais montrée à l'auteur, et la relance reprend la notice.
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

Refuse une notice en préparation, fait fabriquer d'abord les versions allégées manquantes (tâche à part, une seule fois : `_na_apercus_tente`), prend la notice, refait le Word s'il a disparu, puis `expedier()` :

- un seul courriel pour toute la rédaction (destinataires « notices » des réglages), `Reply-To` vers le responsable ;
- pièces jointes composées par `pieces_du_courriel()` : le Word toujours, puis chaque figure dans son ordre, en version allégée si elle en a une, l'original sinon, sous le nom de sa figure (clé du tableau d'attachements de `wp_mail`), tant que le poids réglé n'est pas atteint (`budget_du_courriel()`, en méga-octets décimaux, encodage base 64 compté) ; ce qui ne tient pas est nommé dans un encadré avec le lien de la notice ;
- puis une copie à chaque personne citée, avec le Word et le lien de correction (valable un mois).

Rendu vrai : état `envoyee`, note, effacement du Word temporaire. Rendu faux : `compter_un_essai()`. L'erreur réelle de PHPMailer est captée par `wp_mail_failed` et traduite pour la rédaction (`expliquer_l_erreur()`).

### Tâches planifiées (WP-Cron)

| Crochet | Fréquence | Rôle |
|---|---|---|
| `na_expedier_notice` (argument : id) | ponctuelle | expédier une notice |
| `na_relancer_les_notices` | toutes les cinq minutes (intervalle `na_cinq_minutes` ajouté par l'extension) | reprendre jusqu'à `PAR_PASSAGE` (5) notices en attente ; remettre en route la tâche des termes |
| `na_recapituler_les_notices` | chaque jour à 7 h, heure du site | récapitulatif adressé aux destinataires « recap » |
| `na_fabriquer_les_apercus` (argument : id) | ponctuelle | versions allégées manquantes, puis reprogrammation de l'envoi |
| `na_resoudre_les_termes` | tâche unique, réservée au besoin | lire dans Pactols les termes des notices en attente, par lots (`LOT_TERMES` = 50) et dans un budget de `BUDGET_TERMES` (10 s) |

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
| `_na_remplace` | la référence que ce dépôt corrige, s'il en corrige une |
| `_na_etat`, `_na_prise`, `_na_essais`, `_na_erreur`, `_na_note`, `_na_envoyee_le` | la file |
| `_na_preparation` | marque posée pendant la préparation du dépôt |
| `_na_fichiers` | les fichiers à joindre (chemins) |
| `_na_document` | le Word temporaire, effacé après l'envoi (il se refait à la demande) |
| `_na_illustrations` | les originaux rangés, dans l'ordre des figures |
| `_na_autorisations` | les autorisations de reproduction, par rang de figure |
| `_na_apercus`, `_na_apercus_tente` | les versions allégées par rang, et la marque de leur fabrication |
| `_na_rubrique`, `_na_famille`, `_na_classement`, `_na_responsable`, `_na_courriel` | copies à plat pour trier, filtrer et chercher dans la liste |
| `_na_pactols` | les concepts lus dans Pactols, par ARK (forme préférée, étiquettes, chaîne, date de lecture) |
| `_na_pactols_apres`, `_na_pactols_essais` | l'attente de résolution des termes |
| `_na_historique_figures` | les textes d'accessibilité remplacés par une correction depuis la fiche |

### La saisie (`_na_donnees`)

Clés principales : `rubrique_principale`, `renvoi_1`, `renvoi_2`, `rubrique_matiere` ; `lieux` (liste `{nom, ark}`, le premier est la commune, recopiée dans `commune` et `commune_ark`), `departement`, `lieu_dit`, `annee` ; `nature` et `nature_items` (`{label, ark}`) ; `pactols_periods_items`, `pactols_subjects_items`, `pactols_places_items` (`{label, ark, idConcept, fullpath}`, plus `libre` pour un terme gardé hors de la liste) et leurs versions texte ; `num_autorisation`, `id_patriarche`, `rapport_lien`, `originaux_lien` ; `organismes` ; responsable, co-responsable et co-auteur (`*_prenom`, `*_nom`, `*_inst`, `*_email`) ; `texte_notice` (HTML restreint : `p`, `br`, `em`, `strong`, `sup`, `sub`, `span` de petites capitales) ; `illustrations` (par figure : `rang`, `titre`, `legende`, `credits`, `alt`, `description`, `autorisation`, pixels) ; `commentaires` ; `remplace`.

Les sorties ne lisent jamais `_na_donnees` directement : elles passent par `saisie_de()`, qui ajoute les formes préférées, les aperçus et ce que chaque époque de la saisie demande (`lieux_de()`, `organismes_de()` relisent les notices anciennes). Quand une version change la forme des données, `rattraper_les_anciennes()` met les notices à niveau une fois (`ETAT_DES_DONNEES`, option `na_etat_des_donnees`).

### Options

| Option | Contenu |
|---|---|
| `notice_archeomed_options` | tous les réglages (section 7), y compris les sous-clés `normes` et `styles` |
| `notice_archeomed_accessibilite` | le dernier contrôle axe-core et le dernier audit Ara |
| `notice_archeomed_candidats` | statut et note par terme candidat |
| `na_etat_des_donnees` | version de la forme des données |
| `na_essais_de_poids` | l'historique des essais de poids du courriel |

### Transients

`na_reprise_<jeton>` (saisie gardée pour la reprendre, un mois au plus), `na_reprise_faite_<jeton>`, `na_pactols_<hash>` (réserve de l'autocomplétion relayée par le serveur), réserves du thésaurus (concept lu : un mois ; échec : une heure), compteurs de quotas, `na_essai_<utilisateur>` (résultat d'un essai des réglages), `na_diagnostic_*`, `na_assemblage*`, `na_maj_release` (dernière release GitHub, six heures).

### Fichiers

Les originaux, autorisations, versions allégées et une éventuelle feuille de styles déposée vivent dans `wp-content/uploads/notice-archeomed/`, protégé par un `.htaccess` (`Require all denied`) et un `index.php`. Ils ne se servent que par `admin-post` (`na_illustration`), après vérification du droit et du jeton. Les fichiers de travail passent par le répertoire temporaire (`notice-archeomed-tmp/`, ateliers de dossiers) et sont purgés : un jour en général, un mois pour ce qu'un envoi manqué a mis de côté. Supprimer une notice efface ses fichiers (`before_delete_post`).

## 7. Réglages

### Priorité des valeurs

Pour chaque clé : une constante de `wp-config.php` si elle existe (`NA_TURNSTILE_SECRET`, `NA_TURNSTILE_SITE`, `NA_DEST_EMAIL`, `NA_MODE_ENVOI`, `NA_PROTECTION`), sinon la valeur enregistrée, sinon le défaut du code (`Notice_Archeomed_Settings::$defaults`). Un champ verrouillé par une constante est grisé dans la page.

### Onglets

Destinataires · Numéro et iconographie · Normes éditoriales · Styles Métopes · Formulaire · Accessibilité · Candidats à Pactols · Courriel · Feuille de styles · Mises à jour · Hébergement · Documentation. Chaque onglet a son propre formulaire. Un onglet est une méthode `onglet_<clé>()` de la classe des réglages ; en ajouter un, c'est ajouter sa clé dans `onglets()` et sa méthode.

### Clés principales de `notice_archeomed_options`

- **Destinataires** : `destinataires` (liste d'adresses, chacune cochée pour « notices » et/ou « recap »).
- **Protection** : `protection` (`locale`, `turnstile`, `les_deux`), `turnstile_site`, `turnstile_secret`.
- **Envoi** : `mode_envoi` (`differe`, `immediat`), `envoi_mode` (`php` ou `smtp`) et `smtp_*`, `poids_courriel` (Mo).
- **Iconographie** : `numero`, `nom_modele`, `br_*` (basse définition : largeur, ppp, poids, tolérance), `hr_*` (haute définition des photographies et des traits), `garder_originaux`.
- **Pactols** : `pactols_serveur` (`oui`/`non` : le serveur lit-il les termes).
- **Mises à jour** : `maj_github`, `github_depot`, `github_jeton`.
- **`normes`** : les choix éditoriaux, décrits par `Notice_Archeomed_Normes::catalogue()` — siècles (`siecle_mot`, `siecle_chiffres`, `siecle_ordinal`), typographie (`typographie`, `espace_ponctuation`, `annees`), composition (`titre_notice`, `lieu_dit_italique`, `figure_abreviation`, `figure_numero`), dépôt (`photo_largeur_cm`, `photo_hauteur_cm`, `photo_ppp`, `trait_ppp`, `mots_min`, `mots_max`, `alt_conseille`), `sigles` (liste « SIGLE = développement »), `avis` (les familles d'avis données au dépôt). Le code lit une norme par `Notice_Archeomed_Normes::valeur( 'cle' )`.
- **`styles`** : la correspondance de chaque bloc de la notice avec un style de la feuille, décrite par `Notice_Archeomed_Styles::catalogue()`. Le code n'écrit jamais un nom de style en dur : il appelle `Notice_Archeomed_Styles::de( 'bloc' )`. Un choix est refusé s'il n'existe pas dans la feuille installée.

`sanitize()` ne repart pas de zéro : il complète l'option existante, onglet par onglet (les témoins `*_presents` disent qu'un onglet a été soumis), et se protège du double passage que WordPress fait quand l'option n'existe pas encore.

## 8. Le document Word

### Principe

On ne recrée pas la feuille de styles : `Notice_Archeomed_DOCX` ouvre le gabarit (`modele-metopes.docx`, ou celui déposé dans l'onglet Feuille de styles), recopie toutes ses pièces et ne remplace que `word/document.xml`, les relations, le manifeste des types et les propriétés du document. Les styles sont désignés par leur **nom affiché** (« TEI_figure_caption ») et résolus vers l'identifiant interne en lisant `styles.xml`. Remplacer le gabarit suffit donc à suivre une évolution de Métopes. Le style « à supprimer » n'est pas dans la feuille : il est injecté à l'écriture ; il porte tout ce que la rédaction doit lire mais qui ne doit pas être publié (avis, termes non retenus, description d'une figure à placer…).

### API du générateur

`add_paragraph( $style, $runs )` où chaque run est un tableau : `text`, et des drapeaux `b`, `i`, `sup`, `sub`, `pc` (petites capitales), `brut` (aucune typographie : identifiants), `nom` (insécables dans un nom de personne), `sans_siecles`. `add_raw_paragraph( $style, $xml )`, `char_run( $style, $texte )`, `hyperlink( $url, $libellé )`, `image_liee()` (image en lien vers `../icono/br/…`, dans le dossier Métopes) et `image_incluse()` (octets dans le fichier, pour le Word envoyé par courriel), `html_to_paragraphs()` (le HTML de l'éditeur en paragraphes), `write_to( $chemin )`.

### Ce que le générateur fait au texte

- **Typographie française** (`Notice_Archeomed_Typographie::corriger()`), selon les normes : insécables, apostrophes courbes, passages en langue étrangère laissés à leur ponctuation, adresses et heures protégées.
- **Siècles** (`DOCX::siecles()`) : « XIIe siècle » devient le chiffre en bas de casse avec l'attribut petites capitales, l'ordinal en exposant, « siècle » abrégé en « s. », selon les normes. Word ne réduit que les minuscules : c'est pourquoi le chiffre s'écrit en bas de casse. Les sorties HTML écrivent au contraire le chiffre en capitales, réduit par `font-variant-caps: all-small-caps`, pour que la synthèse vocale lise un nombre.
- **Caractères interdits en XML** ôtés à l'échappement (`esc()`).
- **Texte alternatif** : dans `descr` du `wp:docPr` de chaque image ; c'est là que Métopes le lit. Le paragraphe `figure_alttext` est facultatif et désactivé par défaut (la conversion Métopes le perd).
- **Description détaillée** : en fin de légende, dans le style du bloc `figure_description` (`TEI_figure_caption` par défaut), ouverte par « Description : ».
- **Langue** : la feuille est déclarée en français ; propriétés du document (titre, dates) renseignées.

### Ordre d'une notice

Avis éventuels (« à supprimer ») ; titre (lieux, département, lieu-dit) ; métadonnées (nature, autres lieux, période, année, numéro d'autorisation, identifiant Patriarche, rapport, organismes, mots-clés — chaque terme Pactols porte son ARK en lien) ; texte ; responsabilités (collées au dernier paragraphe ou dans leur paragraphe, selon le réglage `responsabilites`) ; figures (début de bloc, image, titre avec numéro, légende, description, crédits, autorisation, texte alternatif éventuel, fin de bloc). `remplir_le_document()` est la seule fonction qui écrit une notice : le Word d'une notice, le fascicule et le dossier l'appellent tous.

### Fascicule, relecture et dossier Métopes

- **Fascicule** (`na_fascicule`) : toutes les notices d'une rubrique dans l'ordre du volume (`classement_de()` : rubrique, matière pour la V, famille d'opérations, commune), avec les renvois des autres rubriques à leur place alphabétique.
- **Page de relecture** : la même rubrique en HTML, figures comprises, pour relire dans un navigateur.
- **Dossier Métopes** (`na_paquet`, `Notice_Archeomed_Paquet`) : une archive zip avec `style/` (le Word, images liées), `XML/` (fichier d'indexation), `icono/hr` (haute définition aux normes), `icono/br` (basse définition liée au Word), `icono/originaux`, et un lisez-moi qui dit tout ce qui n'a pas pu être fait. Les images sont nommées par `Notice_Archeomed_Nommage::construire()`.
- **Blocs d'index** (`indexation_de()`) : pour chaque terme lu, l'arbre des concepts du plus général au terme, avec étiquettes en toutes langues, en TEI, dans une enveloppe d'un espace de noms propre à l'extension (constante `ESPACE_INDEXATION`) qui nomme le style du paragraphe où coller chaque zone.

## 9. Pactols

- **Dans le navigateur** : la recherche interroge directement `pactols.frantiq.fr` (CORS ouvert) ; un relais serveur existe aussi (`wp_ajax_na_pactols_search`, réserve d'un jour, quota par connexion). Un terme choisi garde son ARK ; un terme tapé hors de la liste est gardé, marqué `libre`, et n'est pas retenu dans la notice (il paraît « à supprimer » et dans l'onglet Candidats à Pactols).
- **Sur le serveur** : `Notice_Archeomed_Thesaurus::resoudre()` lit le concept par l'API OpenAPI (`/openapi/v1/concept/…`, en-tête `Accept: application/json` obligatoire), son expansion vers le haut, ses étiquettes. Un concept sans chaîne n'est pas résolu ; un échec se retient une heure, pour toutes les notices ; une coupure due au budget n'est pas un échec et se reprend. Les concepts retirés du thésaurus ne sont jamais indexés et leur remplaçant est signalé.
- **Lecture coupable** : si l'hébergement refuse la sortie vers Pactols (proxy), le réglage `pactols_serveur` = `non` coupe la lecture ; les ARK restent dans le Word, et une transformation XML peut en tirer les entrées d'index.

## 10. Le formulaire, côté navigateur

Tout le JavaScript est en ligne dans `render_form()`, sans outil de construction, et doit rester compatible avec les navigateurs courants sans transpilation (ni `?.`, ni assertion arrière dans les expressions régulières). Les données du serveur arrivent par `wp_json_encode` : normes, phrases et motifs des avis (`A11Y`), plafonds réels de PHP. Les avis du navigateur et ceux du serveur doivent rester identiques : **toute modification d'un contrôle se fait des deux côtés**, et les essais comparent les phrases.

Accessibilité : chaque champ a son étiquette et son `aria-describedby`, les erreurs sont listées et reliées à leur champ, les états ne reposent jamais sur la seule couleur, le puzzle a une alternative au clavier. L'onglet Accessibilité fait passer la page publiée à axe-core.

## 11. Sécurité

- Toute action d'administration passe par `admin-post` ou `admin-ajax` avec un jeton (`check_admin_referer`, `wp_verify_nonce`) et une capacité (`manage_options`, ou le droit de lire les notices).
- Entrées : chaque champ est ramené à sa forme (`entrees_attendues()`), nettoyé, borné ; les URL limitées à `http`/`https` ; les ARK au seul domaine de Frantiq ; les téléversements contrôlés par extension, type MIME réel et validité du PDF.
- Sorties : échappement systématique (`esc_html`, `esc_attr`, `esc_url`) ; échappement XML propre au DOCX et au TEI ; cellules CSV protégées contre les formules.
- Fichiers reçus hors de la portée du web ; servis seulement par l'extension.
- Quotas par IP et par adresse ; pot de miel ; délai minimal ; puzzle signé ; Turnstile au choix.
- Scripts tiers chargés avec leur empreinte SRI.
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

`.github/essais/file-des-termes.php` est un script WP-CLI (`wp eval-file`) qui charge l'extension dans un vrai WordPress et vérifie environ deux cents comportements : file et prise atomique, pièces du courriel, document Word (styles, ordre, XML valide), typographie (corpus `typographie.json`), siècles, normes, styles, avis, accessibilité, sigles, Pactols (avec un faux serveur), réglages, candidats… Chaque vérification est une ligne `na_verifier( condition, 'ce qui doit être vrai', $obtenu )`. Un essai calcule, nettoie ce qu'il a créé, puis vérifie.

Pour l'exécuter hors de l'intégration continue : un WordPress avec WP-CLI, l'extension installée et activée, puis `wp eval-file .github/essais/file-des-termes.php`. WordPress Playground (`@wp-playground/cli`) convient aussi, en montant le dossier de l'extension.

**Toute correction de comportement s'accompagne de son essai**, et un essai doit échouer avant la correction.

### Intégration continue (`.github/workflows/publier.yml`)

À chaque poussée : `analyser` (analyseur PHP 7.2 et 8.3, doublons, commentaires) puis `essayer` (matrice PHP 7.2 / WordPress 6.9, PHP 8.1 et 8.3 / dernière version : installation, activation, affichage du formulaire, essais, désactivation, journal sans erreur fatale). Sur une étiquette `vX.Y` : `publier` vérifie que l'étiquette égale la version de l'en-tête, fabrique l'archive et publie la release avec `notice-archeomed.zip`.

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
- **Avis** : jamais bloquants ; même phrase au serveur et au navigateur.
- **Règles acquises** : une notice ne se perd pas parce qu'un courriel échoue ou qu'une métadonnée manque ; deux dépôts simultanés ne se gênent pas ; toute écriture concurrente passe par une écriture conditionnelle.

## 14. Points d'attention

- **Hébergement derrière un proxy** : Turnstile (Cloudflare) et la lecture de Pactols demandent une sortie HTTPS ; à défaut, protection locale et `pactols_serveur` = `non`.
- **Limites de PHP** : `upload_max_filesize` et `post_max_size` bornent les fichiers ; une requête plus lourde que `post_max_size` arrive vide, ce que `refuser_l_envoi_trop_lourd()` détecte et dit à l'auteur.
- **Planificateur** : sans WP-Cron ni tâche système, rien ne part en différé.
- **Concurrence** : à éprouver sur une vraie base MySQL ; les environnements en SQLite (Playground) ne reproduisent pas les écritures simultanées.
- **Évolutions de Métopes** : remplacer le gabarit, lancer `verifier-les-styles`, ajuster la correspondance dans l'onglet Styles Métopes plutôt que le code.
- **Courriel** : le serveur de la revue refuse au-delà d'une dizaine de méga-octets ; l'essai de poids de l'onglet Courriel mesure la limite réelle.
