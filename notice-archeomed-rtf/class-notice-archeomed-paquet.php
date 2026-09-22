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

	private $erreurs = array();
	private $journal = array();
	// Les fichiers que l'on fabrique en chemin. ZipArchive ne lit ses sources
	// qu'à la fermeture : les effacer plus tôt donnerait une archive vide.
	private $temporaires = array();

	public function journal() {
		return $this->journal;
	}

	public function erreurs() {
		return $this->erreurs;
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
		$chemin = trailingslashit( $dossier ) . 'notice-archeomed-' . $nom_paquet . '.zip';
		@unlink( $chemin );

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
						'annee'    => isset( $d['annee'] ) ? $d['annee'] : '',
						'n'        => $n,
					)
				);
				$this->poser_une_illustration( $zip, $source, $nom, $racine );
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
			'icono/originaux/ les fichiers tels que les auteurs les ont envoyés',
			'',
		);
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
	private function reduire( $source, $cible, $largeur, $qualite, $poids_max, $tolerance ) {
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}
		$plancher = (int) round( $poids_max * ( 100 - $tolerance ) / 100 );
		try {
			$image = new Imagick();
			$image->readImage( $source );
			$image->setImageFormat( 'jpeg' );
			// Une image animée ou multipage — un TIFF en porte parfois — ne
			// garde que sa première vue : c'est une figure, pas un film.
			$image = $image->flattenImages();
			if ( $image->getImageWidth() > $largeur ) {
				$image->resizeImage( $largeur, 0, Imagick::FILTER_LANCZOS, 1 );
			}
			for ( $essai = 0; $essai < 6; $essai++ ) {
				$image->setImageCompressionQuality( max( 40, $qualite ) );
				$octets = strlen( $image->getImageBlob() );
				$ko     = (int) round( $octets / 1024 );
				if ( $ko <= $poids_max && ( $ko >= $plancher || $qualite <= 40 ) ) {
					break;
				}
				if ( $ko > $poids_max ) {
					$qualite -= 10;
					if ( $qualite < 40 ) {
						$qualite = 40;
						break;
					}
					continue;
				}
				break;   // déjà sous le plancher : inutile de remonter
			}
			$ok = (bool) $image->writeImage( $cible );
			$image->clear();
			return $ok && file_exists( $cible );
		} catch ( Exception $e ) {
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
			if ( $this->reduire( $source, $tmp, 10000,
				(int) $reglage( 'hr_' . $famille . '_qualite' ), 1024 * 1024, 0 ) ) {
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

		if ( $this->reduire( $source_br, $tmp_br, $largeur, $qualite, $poids, $tolerance ) ) {
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
