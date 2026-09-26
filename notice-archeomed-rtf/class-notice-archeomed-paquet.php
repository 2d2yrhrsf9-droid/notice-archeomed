<?php
/**
 * Le paquet d'une rubrique, aux dossiers de Métopes.
 *
 * La rédaction recevait un document Word par rubrique, et les illustrations
 * une par une depuis la fiche de chaque notice : à elle de les rassembler, de
 * les renommer et de les ranger. Le paquet fait ce travail — l'arborescence
 * que la chaîne attend, les noms assainis, les deux définitions — et le rend
 * en une archive.
 *
 * Ce qu'il ne sait pas faire, il le dit : un « lisez-moi » énumère ce qui
 * n'a pas pu être réduit et pourquoi. Un dossier qui ment sur son contenu
 * coûte plus cher qu'un dossier incomplet.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Paquet {

	/** Les formats qu'on tient pour des photographies, par opposition au trait. */
	const MATRICIELS = array( 'jpg', 'jpeg', 'png', 'tif', 'tiff' );

	/**
	 * Le plafond de poids se compte en kilo-octets.
	 *
	 * « 1024 * 1024 » se lit « un méga-octet » chez tout le monde, et vaudrait
	 * ici un giga-octet. La haute définition n'a pas de plafond : autant le
	 * dire par un nom plutôt que par un nombre qu'on relira de travers.
	 */
	const SANS_PLAFOND = PHP_INT_MAX;

	private $erreurs = array();
	private $journal = array();
	// Les fichiers que l'on fabrique en chemin. ZipArchive ne lit ses sources
	// qu'à la fermeture : les effacer plus tôt donnerait une archive vide.
	private $temporaires = array();
	// Les noms déjà posés dans l'archive. Deux notices d'une même commune et
	// d'une même année donnent le même nom quand le modèle ne porte pas le
	// lieu-dit : sans ce registre, la seconde figure écrase la première dans
	// le zip, sans un mot.
	private $noms_pris = array();

	public function journal() {
		return $this->journal;
	}

	public function erreurs() {
		return $this->erreurs;
	}

	/**
	 * Un nom qui n'a pas encore servi dans cette archive.
	 *
	 * Le modèle devrait suffire à les distinguer — c'est à quoi sert
	 * « {lieu_dit} ». Mais il se règle à la main, et rien n'empêche de l'en
	 * priver : le suffixe est le filet, et il se dit dans le lisez-moi pour
	 * qu'on sache pourquoi un fichier s'appelle « _2 ».
	 */
	private function nom_unique( $nom ) {
		$clef = strtolower( $nom );
		if ( ! isset( $this->noms_pris[ $clef ] ) ) {
			$this->noms_pris[ $clef ] = 1;
			return $nom;
		}
		++$this->noms_pris[ $clef ];
		$suffixe = $nom . '_' . $this->noms_pris[ $clef ];
		$this->journal[] = $nom . ' : nom déjà pris dans cette rubrique, posé sous « '
			. $suffixe . ' ». Ajoutez « {lieu_dit} » au modèle de nom pour les distinguer.';
		$this->noms_pris[ strtolower( $suffixe ) ] = 1;
		return $suffixe;
	}

	/**
	 * Assemble le paquet d'une rubrique et rend le chemin de l'archive.
	 *
	 * « $notices » est une liste de tableaux { d, illustrations } : la saisie
	 * telle qu'elle a été déposée, et les fichiers conservés dans leur ordre
	 * de dépôt — le même que celui des légendes, c'est ainsi que le rang de
	 * figure se tient.
	 *
	 * L'assemblage tient dans la requête de l'administrateur, et c'est
	 * voulu : la règle qui veut que deux dépôts ne se gênent pas protège les
	 * auteurs devant le formulaire, non la rédaction devant sa liste. Un
	 * paquet demandé à la main peut faire attendre celui qui l'a demandé.
	 */
	public function assembler( $rubrique, $notices, $document, &$erreur ) {
		$erreur = '';
		if ( ! class_exists( 'ZipArchive' ) ) {
			$erreur = "L'extension ZipArchive est absente de cet hébergement : le paquet ne peut pas être assemblé.";
			return '';
		}
		$rang   = self::rang_de_rubrique( $rubrique );
		$numero = Notice_Archeomed_Settings::get( 'numero' );
		$modele = Notice_Archeomed_Settings::get( 'nom_modele' );

		$nom_paquet = Notice_Archeomed_Nommage::assainir(
			( '' !== $numero ? $numero . '_' : '' ) . $rang . '_'
			. preg_replace( '/^[IVX]+\.\s*/u', '', $rubrique ), 'paquet' );

		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		if ( ! is_dir( $dossier ) ) {
			wp_mkdir_p( $dossier );
		}
		// Le nom porte un tirage : deux assemblages simultanés de la même
		// rubrique se détruisaient l'un l'autre, et le premier téléchargement
		// arrivait tronqué sans que rien ne le dise.
		$chemin = trailingslashit( $dossier ) . 'notice-archeomed-' . $nom_paquet
			. '-' . wp_generate_password( 8, false, false ) . '.zip';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$erreur = "Création de l'archive impossible.";
			return '';
		}
		$racine = $nom_paquet . '/';
		// Les dossiers vides se déclarent : sans cela, « XML » n'existerait
		// pas à l'ouverture et l'on croirait à un oubli.
		foreach ( array( 'style', 'XML', 'icono', 'icono/hr', 'icono/br' ) as $d ) {
			$zip->addEmptyDir( $racine . $d );
		}
		if ( (int) Notice_Archeomed_Settings::get( 'garder_originaux' ) ) {
			$zip->addEmptyDir( $racine . 'icono/originaux' );
		}

		if ( '' !== $document && file_exists( $document ) ) {
			$zip->addFile( $document, $racine . 'style/' . $nom_paquet . '.docx' );
		}

		$comptes = array( 'notices' => 0, 'illustrations' => 0 );
		foreach ( (array) $notices as $notice ) {
			$d = isset( $notice['d'] ) ? $notice['d'] : array();
			if ( empty( $d ) ) {
				continue;
			}
			++$comptes['notices'];
			$fichiers = isset( $notice['illustrations'] ) ? (array) $notice['illustrations'] : array();
			$n = 0;
			foreach ( $fichiers as $source ) {
				if ( ! file_exists( $source ) ) {
					continue;
				}
				++$n;
				++$comptes['illustrations'];
				$nom = Notice_Archeomed_Nommage::construire(
					$modele,
					array(
						'numero'   => $numero,
						'rubrique' => $rang,
						'commune'  => isset( $d['commune'] ) ? $d['commune'] : '',
						'lieu_dit' => isset( $d['lieu_dit'] ) ? $d['lieu_dit'] : '',
						'annee'    => isset( $d['annee'] ) ? $d['annee'] : '',
						'n'        => $n,
					)
				);
				$this->poser_une_illustration( $zip, $source, $this->nom_unique( $nom ), $racine );
			}
		}

		$zip->addFromString( $racine . 'lisez-moi.txt',
			$this->lisez_moi( $rubrique, $comptes ) );
		$zip->close();

		foreach ( $this->temporaires as $t ) {
			@unlink( $t );
		}
		$this->temporaires = array();
		return file_exists( $chemin ) ? $chemin : '';
	}

	/**
	 * Ce que le paquet contient, et ce qu'il n'a pas pu faire.
	 */
	private function lisez_moi( $rubrique, $comptes ) {
		$lignes = array(
			'Paquet de la rubrique : ' . $rubrique,
			'Assemblé le ' . date_i18n( 'j F Y à H:i' ),
			'',
			(int) $comptes['notices'] . ' notice(s), '
				. (int) $comptes['illustrations'] . ' illustration(s).',
			'',
			'style/           le document Word de la rubrique, aux styles Métopes',
			'XML/             vide : c\'est la chaîne qui le remplira',
			'icono/hr/        la haute définition, pour la mise en page',
			'icono/br/        la basse définition, appelée en lien par le document',
		);
		if ( (int) Notice_Archeomed_Settings::get( 'garder_originaux' ) ) {
			$lignes[] = 'icono/originaux/ les fichiers tels que les auteurs les ont envoyés';
		}
		$lignes[] = '';
		if ( empty( $this->journal ) && empty( $this->erreurs ) ) {
			$lignes[] = 'Toutes les illustrations ont été traitées.';
		} else {
			$lignes[] = 'Ce qui n\'a pas pu être fait :';
			foreach ( array_merge( $this->journal, $this->erreurs ) as $ligne ) {
				$lignes[] = '  - ' . $ligne;
			}
		}
		return implode( "\r\n", $lignes );
	}

	/**
	 * Le rang d'une rubrique, tiré de son numéro romain.
	 *
	 * « III. Constructions et habitats fortifiés » vaut 3. Il entre dans le
	 * nom des fichiers, comme la mise en page le fait : « AM55_3_… ».
	 */
	public static function rang_de_rubrique( $rubrique ) {
		if ( ! preg_match( '/^([IVX]+)\./u', trim( (string) $rubrique ), $m ) ) {
			return 0;
		}
		$valeurs = array( 'I' => 1, 'V' => 5, 'X' => 10 );
		$romain  = strtoupper( $m[1] );
		$total   = 0;
		$long    = strlen( $romain );
		for ( $i = 0; $i < $long; $i++ ) {
			$ici = isset( $valeurs[ $romain[ $i ] ] ) ? $valeurs[ $romain[ $i ] ] : 0;
			$apres = ( $i + 1 < $long && isset( $valeurs[ $romain[ $i + 1 ] ] ) )
				? $valeurs[ $romain[ $i + 1 ] ] : 0;
			$total += ( $ici < $apres ) ? -$ici : $ici;
		}
		return $total;
	}

	/**
	 * L'aperçu d'un EPS binaire, lu dans son en-tête.
	 *
	 * Un EPS d'Illustrator n'est pas qu'un programme PostScript : le format
	 * DOS EPS place en tête trente octets qui donnent la position d'un aperçu
	 * TIFF. Il se lit avec un déplacement et une lecture — sans Ghostscript,
	 * sans interpréteur, sans rien qu'on ne veuille mettre sur un serveur.
	 *
	 * C'est ce qui permet de fabriquer une basse définition d'un vectoriel
	 * là où aucun outil ne saurait le rastériser. L'aperçu fait quelques
	 * centaines de pixels : c'est trop peu pour imprimer, et c'est
	 * exactement ce qu'on demande à une basse définition.
	 *
	 * Rend le contenu du TIFF, ou une chaîne vide.
	 */
	public static function apercu_eps( $chemin ) {
		$h = @fopen( $chemin, 'rb' );
		if ( ! $h ) {
			return '';
		}
		$entete = fread( $h, 30 );
		if ( false === $entete || strlen( $entete ) < 30
			|| "\xC5\xD0\xD3\xC6" !== substr( $entete, 0, 4 ) ) {
			fclose( $h );
			return '';
		}
		// Petit-boutien : position à l'octet 20, longueur à l'octet 24.
		$champs = unpack( 'Vposition/Vlongueur', substr( $entete, 20, 8 ) );
		if ( empty( $champs['longueur'] ) ) {
			fclose( $h );
			return '';   // un EPS sans aperçu : il en existe, on n'insiste pas
		}
		fseek( $h, (int) $champs['position'] );
		$tiff = fread( $h, (int) $champs['longueur'] );
		fclose( $h );
		return is_string( $tiff ) ? $tiff : '';
	}

	/** Imagick est-elle là, et sait-elle lire ce format ? */
	private static function imagick_sait( $format ) {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}
		$connus = (array) Imagick::queryFormats( strtoupper( $format ) );
		return ! empty( $connus );
	}

	/**
	 * Réduit une image jusqu'à tenir sous le plafond de poids.
	 *
	 * La qualité baisse par paliers, et l'on s'arrête dès que le fichier
	 * entre dans la fourchette — insister au-delà abîmerait l'image sans
	 * gagner d'octets utiles.
	 *
	 * Rend vrai si la cible a été écrite.
	 */
	private function reduire( $source, $cible, $largeur, $qualite, $dpi, $poids_max, $tolerance ) {
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}
		$plancher = ( self::SANS_PLAFOND === $poids_max )
			? 0 : (int) round( $poids_max * ( 100 - $tolerance ) / 100 );
		$image = null;
		try {
			$image = new Imagick();
			// « [0] » demande la première vue seulement. Un PDF de deux pages
			// ou un TIFF multi-images chargeait toutes ses vues, que
			// « flattenImages » superposait ensuite en une bouillie : ce
			// n'est pas la première page qu'on obtenait, c'est leur somme.
			// Lire la seule vue utile corrige l'image et épargne la mémoire.
			try {
				$image->readImage( $source . '[0]' );
			} catch ( Exception $e ) {
				$image->clear();
				$image->readImage( $source );
			}
			// Ce qui reste de calques dans cette vue s'aplatit. L'objet
			// d'origine se libère : sur une centaine d'illustrations, les
			// abandonner l'un après l'autre finissait par épuiser la mémoire.
			$plat = $image->flattenImages();
			if ( $plat instanceof Imagick ) {
				$image->clear();
				$image->destroy();
				$image = $plat;
			}
			$image->setImageFormat( 'jpeg' );
			if ( $image->getImageWidth() > $largeur ) {
				$image->resizeImage( $largeur, 0, Imagick::FILTER_LANCZOS, 1 );
			}
			// La résolution s'inscrit, elle ne se fabrique pas : porter une
			// image à 1200 dpi en inventant des pixels l'abîmerait sans rien
			// apporter. On enregistre la densité voulue, et c'est elle que la
			// mise en page lira pour savoir à quelle taille poser la figure.
			if ( $dpi > 0 ) {
				$image->setImageUnits( Imagick::RESOLUTION_PIXELSPERINCH );
				$image->setImageResolution( $dpi, $dpi );
			}

			$qualite = max( 40, (int) $qualite );
			for ( $essai = 0; $essai < 6; $essai++ ) {
				$image->setImageCompressionQuality( $qualite );
				$ko = (int) round( strlen( $image->getImageBlob() ) / 1024 );
				if ( $ko <= $poids_max ) {
					break;   // dans la fourchette, ou sans plafond
				}
				if ( $qualite <= 40 ) {
					break;   // on ne descend pas plus bas
				}
				$qualite = max( 40, $qualite - 10 );
			}
			// La qualité retenue est posée une dernière fois avant l'écriture :
			// la boucle pouvait sortir en ayant calculé un palier sans
			// l'appliquer, et le fichier s'écrivait alors au palier précédent.
			$image->setImageCompressionQuality( $qualite );
			// Descendue au plancher sans tenir sous le plafond : le fichier
			// part quand même, et le lisez-moi le dit plutôt que de laisser
			// croire que le réglage a été respecté.
			if ( self::SANS_PLAFOND !== $poids_max && $ko > $poids_max ) {
				$this->journal[] = basename( $cible ) . ' : ' . $ko . ' Ko après compression, '
					. 'au-delà du plafond de ' . (int) $poids_max . ' Ko. Qualité descendue à '
					. $qualite . ' sans y parvenir ; réduisez la largeur maximale.';
			}
			$ok = (bool) $image->writeImage( $cible );
			$image->clear();
			$image->destroy();
			return $ok && file_exists( $cible );
		} catch ( Exception $e ) {
			if ( $image instanceof Imagick ) {
				$image->clear();
			}
			$this->erreurs[] = basename( $source ) . ' : ' . $e->getMessage();
			return false;
		}
	}

	/**
	 * Écrit les deux définitions d'une illustration, et rend leurs noms.
	 *
	 * « originaux » garde le fichier tel que l'auteur l'a envoyé. « hr » part
	 * à la mise en page, « br » sert au lien posé dans le document.
	 */
	private function poser_une_illustration( $zip, $source, $nom_sans_ext, $racine ) {
		$ext     = strtolower( pathinfo( $source, PATHINFO_EXTENSION ) );
		$photo   = in_array( $ext, self::MATRICIELS, true );
		$reglage = function ( $clef ) {
			return Notice_Archeomed_Settings::get( $clef );
		};
		$pose = array( 'hr' => '', 'br' => '' );

		if ( (int) $reglage( 'garder_originaux' ) ) {
			$zip->addFile( $source, $racine . 'icono/originaux/' . $nom_sans_ext . '.' . $ext );
		}

		// — La haute définition —
		$famille = $photo ? 'photo' : 'trait';
		$en_jpeg = (int) $reglage( 'hr_' . $famille . '_jpeg' );
		$fait_hr = false;
		if ( $en_jpeg && self::imagick_sait( $ext ) ) {
			$tmp = wp_tempnam( 'na-hr' );
			$this->temporaires[] = $tmp;
			// La haute définition ne se rétrécit pas et n'a pas de plafond :
			// elle part à la mise en page, qui la veut entière.
			if ( $this->reduire( $source, $tmp, PHP_INT_MAX,
				(int) $reglage( 'hr_' . $famille . '_qualite' ),
				(int) $reglage( 'hr_' . $famille . '_dpi' ),
				self::SANS_PLAFOND, 0 ) ) {
				$zip->addFile( $tmp, $racine . 'icono/hr/' . $nom_sans_ext . '.jpg' );
				$pose['hr'] = $nom_sans_ext . '.jpg';
				$fait_hr    = true;
			}
		}
		if ( ! $fait_hr ) {
			$zip->addFile( $source, $racine . 'icono/hr/' . $nom_sans_ext . '.' . $ext );
			$pose['hr'] = $nom_sans_ext . '.' . $ext;
			if ( $en_jpeg ) {
				$this->journal[] = $nom_sans_ext . '.' . $ext
					. ' : haute définition non convertie (format illisible sur ce serveur), original recopié.';
			}
		}

		// — La basse définition —
		$largeur   = (int) $reglage( 'br_largeur' );
		$qualite   = (int) $reglage( 'br_qualite' );
		$poids     = (int) $reglage( 'br_poids' );
		$tolerance = (int) $reglage( 'br_tolerance' );
		$tmp_br    = wp_tempnam( 'na-br' );
		$this->temporaires[] = $tmp_br;
		$source_br = $source;
		$provisoire = '';

		// Un vectoriel ne se rastérise pas ici : on prend l'aperçu que le
		// fichier porte en lui.
		if ( 'eps' === $ext ) {
			$apercu = self::apercu_eps( $source );
			if ( '' !== $apercu ) {
				$provisoire = wp_tempnam( 'na-apercu' );
				file_put_contents( $provisoire, $apercu );
				$source_br = $provisoire;
			}
		}

		if ( $this->reduire( $source_br, $tmp_br, $largeur, $qualite,
			(int) $reglage( 'br_dpi' ), $poids, $tolerance ) ) {
			$zip->addFile( $tmp_br, $racine . 'icono/br/' . $nom_sans_ext . '.jpg' );
			$pose['br'] = $nom_sans_ext . '.jpg';
		} else {
			$this->journal[] = $nom_sans_ext . '.' . $ext
				. ' : pas de basse définition (Imagick absente ou format illisible). Le lien du document restera vide.';
		}
		if ( '' !== $provisoire ) {
			@unlink( $provisoire );
		}
		return $pose;
	}
}
