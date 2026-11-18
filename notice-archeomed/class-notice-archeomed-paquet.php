<?php
/**
 * Le paquet d'une rubrique, aux dossiers de Métopes (chaîne d'édition XML créée par le Pôle document numérique et l'infrastructure Métopes de l'université de Caen Normandie, https://www.metopes.fr).
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

	/**
	 * L'aperçu incorporé au document envoyé par courriel.
	 *
	 * Il n'a pas à valoir la basse définition du dossier : celle-là part à la
	 * mise en page, celui-ci sert à voir. Mille pixels et cent cinquante
	 * kilo-octets donnent une figure lisible à l'écran et à l'impression de
	 * bureau, et trois illustrations n'ajoutent pas un demi-méga au courriel.
	 */
	const LARGEUR_APERCU = 1000;
	/** Au-delà, une image se déclare trop grande pour qu'on tente de la lire : mémoire, temps. */
	const MEGAPIXELS_MAX = 250;
	/** Au plus, les pixels d'une page de PDF dessinée : une A4 à 600 ppp. */
	const MEGAPIXELS_PDF = 36;
	/** La plus grande figure que la page de la revue imprime, en centimètres. */
	const PLEINE_PAGE_CM = array( 17, 24 );
	const POIDS_APERCU   = 150;

	/**
	 * La qualité dont part une basse définition, et le plancher où elle
	 * s'arrête.
	 *
	 * Elle ne se règle plus : on veut la meilleure image que le plafond de
	 * poids admette, et c'est au plugin de la chercher, non à qui dépose une
	 * notice de deviner un pourcentage. Les paliers de huit points laissent
	 * huit essais entre le haut et le bas — assez fins pour ne pas sacrifier
	 * vingt points de qualité quand deux suffisaient.
	 */
	const QUALITE_HAUTE   = 92;
	const QUALITE_PLANCHER = 40;
	const QUALITE_PALIER   = 8;

	private $erreurs = array();
	private $journal = array();
	// Les noms déjà posés dans l'archive. Deux notices d'une même commune et
	// d'une même année donnent le même nom quand le modèle ne porte pas le
	// lieu-dit : sans ce registre, la seconde figure écrase la première dans
	// le zip, sans un mot.
	private $noms_pris = array();
	// Les fichiers d'indexation ont leur propre registre. Ils se nomment
	// d'après la commune et le lieu-dit, sans passer par le modèle de nom des
	// illustrations : les mêler aurait fait conseiller, en cas de collision,
	// d'ajouter « {lieu_dit} » à un modèle que ces fichiers n'ont jamais
	// consulté — un conseil que rien n'aurait pu suivre.
	private $noms_indexation = array();
	// Ce que le dossier dit de lui-même hors des illustrations : l'indexation,
	// ses fichiers. Ces avis avaient leur place dans le journal des images, si
	// bien qu'une rubrique sans illustration les taisait — le lisez-moi y
	// annonçait qu'il n'y avait rien d'anormal, et sautait tout le reste.
	private $notes = array();
	private $atelier    = '';
	// L'atelier garde-t-il les fichiers tels que reçus ? L'archive, selon le
	// réglage ; l'export sur sDrive, jamais.
	private $avec_originaux = false;
	private $nom_paquet = '';
	private $rubrique   = '';
	private $rang       = 0;
	private $comptes    = array( 'notices' => 0, 'illustrations' => 0 );

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
		$libre = self::nom_libre( $nom, $this->noms_pris );
		if ( $libre !== $nom ) {
			$this->journal[] = $nom . ' : nom déjà pris dans cette rubrique, posé sous « '
				. $libre . ' ». Ajoutez « {lieu_dit} » au modèle de nom pour les distinguer.';
		}
		return $libre;
	}

	/**
	 * Le premier nom libre dans un registre, qu'il inscrit : le nom lui-même,
	 * ou le nom suivi de « _2 », « _3 »…
	 *
	 * Le suffixe se vérifiait mal : on prenait « _2 » sans regarder si
	 * « _2 » était déjà pris. Une notice « Parcelle 2 » puis deux notices
	 * « Parcelle », et la seconde recevait le nom de la première — que
	 * l'écriture remplaçait sans un mot. On avance donc jusqu'à un nom que le
	 * registre ne connaît pas.
	 *
	 * Les noms se comparent en minuscules : deux fichiers qui ne diffèrent que
	 * par la casse se confondent sur plus d'un système.
	 */
	private static function nom_libre( $nom, &$registre ) {
		$candidat = $nom;
		for ( $n = 2; isset( $registre[ strtolower( $candidat ) ] ); $n++ ) {
			$candidat = $nom . '_' . $n;
		}
		$registre[ strtolower( $candidat ) ] = true;
		return $candidat;
	}

	/**
	 * Ajoute une ligne au lisez-moi. Ce que le dossier sait de lui-même doit
	 * s'y lire, qu'il vienne des images ou d'ailleurs.
	 */
	public function noter( $ligne ) {
		$this->notes[] = (string) $ligne;
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
	public function preparer( $rubrique, $notices, &$erreur ) {
		$erreur = '';
		if ( ! class_exists( 'ZipArchive' ) ) {
			$erreur = "L'extension ZipArchive est absente de cet hébergement : le paquet ne peut pas être assemblé.";
			return array();
		}
		if ( ! $this->commencer( $rubrique, $erreur, (bool) (int) Notice_Archeomed_Settings::get( 'garder_originaux' ) ) ) {
			return array();
		}
		$plan = array();
		// Une fonction à deux arguments : « file_exists » en refuse un
		// second sous PHP 8.
		$existe = function ( $source ) {
			return file_exists( $source );
		};
		foreach ( $this->planifier( $notices, $existe, false ) as $figure ) {
			if ( ! isset( $plan[ $figure['notice'] ] ) ) {
				$plan[ $figure['notice'] ] = array();
			}
			$plan[ $figure['notice'] ][ $figure['n'] ] = $this->poser_une_illustration(
				$figure['source'], $figure['nom'], $figure['conversions'] );
		}
		// Une notice sans figure posée garde sa place dans le plan.
		foreach ( array_keys( (array) $notices ) as $rang_notice ) {
			if ( ! isset( $plan[ $rang_notice ] ) ) {
				$plan[ $rang_notice ] = array();
			}
		}
		return $plan;
	}

	/**
	 * Nomme le paquet d'une rubrique et ouvre son atelier.
	 *
	 * « $originaux » dit si l'atelier garde aussi les fichiers tels que reçus :
	 * l'archive les porte quand on le règle, l'export sur sDrive jamais — ils
	 * y sont déjà, sous « originaux/ ».
	 */
	public function commencer( $rubrique, &$erreur, $originaux = false ) {
		$erreur         = '';
		$this->rubrique = $rubrique;
		$this->rang     = self::rang_de_rubrique( $rubrique );
		$numero         = Notice_Archeomed_Settings::get( 'numero' );

		$this->nom_paquet = Notice_Archeomed_Nommage::assainir(
			( '' !== $numero ? $numero . '_' : '' ) . $this->rang . '_'
			. preg_replace( '/^[IVX]+\.\s*/u', '', $rubrique ), 'paquet' );
		$this->avec_originaux = (bool) $originaux;
		return $this->atelier_neuf( $erreur );
	}

	/**
	 * Un atelier vide, à la place du précédent : un dossier de travail où
	 * les fichiers se posent avant d'être emballés ou envoyés. Il faut qu'ils
	 * existent pour de bon avant que le document soit écrit — c'est en les
	 * mesurant qu'on sait à quelle taille poser chaque figure.
	 */
	public function atelier_neuf( &$erreur ) {
		$erreur = '';
		$this->nettoyer();
		$this->atelier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/'
			. 'atelier-' . wp_generate_password( 10, false, false );
		foreach ( array( 'style', 'XML', 'icono/hr', 'icono/br' ) as $d ) {
			wp_mkdir_p( $this->atelier . '/' . $d );
		}
		if ( $this->avec_originaux ) {
			wp_mkdir_p( $this->atelier . '/icono/originaux' );
		}
		if ( ! is_dir( $this->atelier ) ) {
			$erreur = "Le dossier de travail n'a pas pu être créé.";
			$this->atelier = '';
			return false;
		}
		return true;
	}

	/**
	 * Les figures à poser, dans l'ordre, chacune avec son nom dans le
	 * dossier ; ce qui manque se dit dans le lisez-moi.
	 *
	 * « $existe » dit si un fichier se trouve : sur le serveur pour
	 * l'archive, sur le serveur ou sur sDrive pour l'export. Une figure
	 * envoyée à part est écartée avec sa ligne « à reprendre », sauf quand
	 * « $a_part » demande de la garder : l'export cherche alors son original.
	 */
	public function planifier( $notices, $existe, $a_part ) {
		$numero  = Notice_Archeomed_Settings::get( 'numero' );
		$modele  = Notice_Archeomed_Settings::get( 'nom_modele' );
		$figures = array();
		foreach ( (array) $notices as $rang_notice => $notice ) {
			$d = isset( $notice['d'] ) ? $notice['d'] : array();
			if ( empty( $d ) ) {
				continue;
			}
			++$this->comptes['notices'];
			$fichiers   = isset( $notice['illustrations'] ) ? (array) $notice['illustrations'] : array();
			// Les légendes et les fichiers se répondent par leur rang. Quand
			// leurs nombres diffèrent — un fichier déposé sans légende, une
			// légende sans fichier —, l'appariement se décale en silence : on
			// le dit plutôt que de laisser croire à une figure bien posée.
			$legendes = isset( $notice['d']['illustrations'] )
				? count( (array) $notice['d']['illustrations'] ) : 0;
			if ( $legendes !== count( $fichiers ) ) {
				$this->journal[] = ( isset( $notice['d']['commune'] ) ? $notice['d']['commune'] : 'Notice' )
					. ' : ' . count( $fichiers ) . ' fichier(s) pour ' . $legendes
					. ' légende(s). Les figures sont appariées par leur rang — vérifiez qu\'elles se correspondent.';
			}
			$n = 0;
			foreach ( $fichiers as $source ) {
				// Le rang du fichier, qu'il soit là ou non : un fichier perdu
				// ne décale pas les suivants, qui garderaient sinon le nom et
				// la légende de la figure d'avant.
				++$n;
				// Une figure envoyée à part n'a sur le site qu'un fichier
				// témoin : rien à réduire. Elle garde son rang, le document
				// pose à sa place le cadre rouge, et le lisez-moi la donne à
				// reprendre avec de quoi la retrouver.
				$envoyee_a_part = class_exists( 'Notice_Archeomed_Pactols' ) && Notice_Archeomed_Pactols::est_a_part( $source );
				if ( $envoyee_a_part && ! $a_part ) {
					$this->journal[] = $this->figure_a_reprendre( $d, $n );
					continue;
				}
				if ( ! $envoyee_a_part && ! call_user_func( $existe, $source, $notice ) ) {
					// Un fichier reçu puis perdu — effacé du serveur, purgé —
					// s'écartait sans un mot : le lisez-moi disait alors qu'il
					// n'y avait « aucune illustration, et c'est normal ».
					$this->journal[] = basename( (string) $source )
						. ' : fichier reçu mais introuvable sur le serveur ; la figure ' . $n . ' manque au dossier.';
					continue;
				}
				if ( ! $envoyee_a_part ) {
					++$this->comptes['illustrations'];
				}
				$nom = $this->nom_unique( Notice_Archeomed_Nommage::construire(
					$modele,
					array(
						'numero'   => $numero,
						'rubrique' => $this->rang,
						'commune'  => isset( $d['commune'] ) ? $d['commune'] : '',
						'lieu_dit' => isset( $d['lieu_dit'] ) ? $d['lieu_dit'] : '',
						'annee'    => isset( $d['annee'] ) ? $d['annee'] : '',
						'n'        => $n,
					)
				) );
				$figures[] = array(
					'notice'      => $rang_notice,
					'id'          => isset( $notice['id'] ) ? (int) $notice['id'] : 0,
					'n'           => $n,
					'source'      => (string) $source,
					'nom'         => $nom,
					'conversions' => isset( $notice['conversions'][ $n ] ) && is_array( $notice['conversions'][ $n ] )
						? $notice['conversions'][ $n ] : array(),
					'a_part'      => $envoyee_a_part ? self::a_part_de( $d, $n ) : null,
					'reprendre'   => $envoyee_a_part ? $this->figure_a_reprendre( $d, $n ) : '',
				);
			}
		}
		return $figures;
	}

	/** Ce que l'auteur a dit d'une figure envoyée à part : fichier, lien, date. */
	private static function a_part_de( $d, $n ) {
		foreach ( isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array() as $item ) {
			if ( isset( $item['rang'] ) && (int) $item['rang'] === (int) $n ) {
				$a_part = Notice_Archeomed_Pactols::figure_a_part( $item );
				return is_array( $a_part ) ? $a_part : array();
			}
		}
		return array();
	}

	/**
	 * Pose une figure dans l'atelier : ses deux définitions, et la mesure de
	 * la basse, ou null si elle n'a pas pu être faite — le journal dit
	 * pourquoi.
	 */
	public function poser_une_figure( $source, $nom_sans_ext, $conversions = array() ) {
		return $this->poser_une_illustration( $source, $nom_sans_ext, (array) $conversions );
	}

	/** Une ligne de plus parmi ce qui n'a pas pu être fait. */
	public function signaler( $ligne ) {
		$this->journal[] = (string) $ligne;
	}

	/** Une figure de plus au compte : un original envoyé à part, retrouvé. */
	public function compter_une_illustration() {
		++$this->comptes['illustrations'];
	}

	/**
	 * Réserve des noms déjà pris ailleurs dans le même dossier : l'export
	 * range toutes les rubriques d'un numéro sous les mêmes « icono/hr » et
	 * « XML/indexation », où un nom repris écraserait la figure d'une autre.
	 */
	public function reserver( $figures, $indexations ) {
		foreach ( (array) $figures as $nom ) {
			$this->noms_pris[ strtolower( (string) $nom ) ] = true;
		}
		foreach ( (array) $indexations as $nom ) {
			$this->noms_indexation[ strtolower( (string) $nom ) ] = true;
		}
	}

	public function atelier() {
		return $this->atelier;
	}

	public function nom_paquet() {
		return $this->nom_paquet;
	}

	/** Combien d'avis le lisez-moi porte : ce qui n'a pas pu être fait. */
	public function avis() {
		return count( $this->journal ) + count( $this->erreurs );
	}

	/**
	 * Ce que le paquet sait de lui-même, pour reprendre l'export au passage
	 * suivant de la tâche. L'atelier n'en est pas : chaque passage ouvre le
	 * sien.
	 */
	public function etat() {
		return array(
			'erreurs'         => $this->erreurs,
			'journal'         => $this->journal,
			'noms_pris'       => $this->noms_pris,
			'noms_indexation' => $this->noms_indexation,
			'notes'           => $this->notes,
			'nom_paquet'      => $this->nom_paquet,
			'rubrique'        => $this->rubrique,
			'rang'            => $this->rang,
			'comptes'         => $this->comptes,
		);
	}

	/** Un paquet tel que « etat » l'a laissé, sans atelier. */
	public static function reprendre( $etat ) {
		$paquet = new self();
		foreach ( (array) $etat as $clef => $valeur ) {
			if ( property_exists( $paquet, $clef ) && 'atelier' !== $clef ) {
				$paquet->$clef = $valeur;
			}
		}
		return $paquet;
	}

	/**
	 * La ligne du lisez-moi d'une figure envoyée à part : la notice, le
	 * numéro de la figure, le fichier dans l'envoi et le lien.
	 */
	private function figure_a_reprendre( $d, $n ) {
		$a_part = null;
		foreach ( isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array() as $item ) {
			if ( isset( $item['rang'] ) && (int) $item['rang'] === (int) $n ) {
				$a_part = Notice_Archeomed_Pactols::figure_a_part( $item );
				break;
			}
		}
		$ligne = ( isset( $d['commune'] ) ? $d['commune'] : 'Notice' )
			. ( ! empty( $d['reference'] ) ? ' (réf. ' . $d['reference'] . ')' : '' )
			. ' : ' . Notice_Archeomed_Normes::numero_de_figure( $n )
			. ' envoyée à part par l’auteur, à reprendre d’après l’original';
		if ( ! empty( $a_part['fichier'] ) ) {
			$ligne .= ' (« ' . $a_part['fichier'] . ' »)';
		}
		if ( ! empty( $a_part['lien'] ) ) {
			$ligne .= ' — ' . $a_part['lien'];
		}
		return $ligne . '.';
	}

	/**
	 * Emballe l'atelier et le document dans une archive, et rend son chemin.
	 */
	public function emballer( $document, $relecture, &$erreur ) {
		$erreur = '';
		if ( '' === $this->atelier || ! is_dir( $this->atelier ) ) {
			$erreur = "Aucun dossier de travail à emballer.";
			return '';
		}
		// Le nom porte un tirage : deux assemblages simultanés de la même
		// rubrique se détruisaient l'un l'autre, et le premier téléchargement
		// arrivait tronqué sans que rien ne le dise.
		$chemin = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/'
			. 'notice-archeomed-' . $this->nom_paquet
			. '-' . wp_generate_password( 8, false, false ) . '.zip';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$erreur = "Création de l'archive impossible.";
			return '';
		}
		$racine = $this->nom_paquet . '/';
		if ( '' !== $document && file_exists( $document ) ) {
			$zip->addFile( $document, $racine . 'style/' . $this->nom_paquet . '.docx' );
		}
		// L'atelier tel qu'il est, dossiers vides compris : « XML » doit
		// exister à l'ouverture, sans quoi on croit à un oubli.
		$this->verser_le_dossier( $zip, $this->atelier, $racine );
		if ( '' !== $relecture ) {
			$zip->addFromString( $racine . 'relecture.html', $relecture );
		}
		$zip->addFromString( $racine . 'lisez-moi.txt', $this->lisez_moi() );
		$zip->close();
		return file_exists( $chemin ) ? $chemin : '';
	}

	/** Recopie un dossier dans l'archive, récursivement. */
	private function verser_le_dossier( $zip, $dossier, $prefixe ) {
		$entrees = @scandir( $dossier );
		if ( false === $entrees ) {
			return;
		}
		$vide = true;
		foreach ( $entrees as $e ) {
			if ( '.' === $e || '..' === $e ) {
				continue;
			}
			$vide = false;
			$chemin = $dossier . '/' . $e;
			if ( is_dir( $chemin ) ) {
				$this->verser_le_dossier( $zip, $chemin, $prefixe . $e . '/' );
			} else {
				$zip->addFile( $chemin, $prefixe . $e );
			}
		}
		if ( $vide ) {
			$zip->addEmptyDir( rtrim( $prefixe, '/' ) );
		}
	}

	/**
	 * Efface les ateliers restés en plan, plus vieux que « $age » secondes.
	 *
	 * L'atelier ne s'efface qu'en fin de requête : un assemblage tué en route
	 * — durée, mémoire — laissait pour toujours les figures d'une rubrique en
	 * trois définitions, des centaines de méga-octets que la purge des
	 * fichiers temporaires ne voyait pas, faute de regarder les dossiers.
	 */
	public static function purger_les_ateliers( $age ) {
		$ateliers = glob( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/atelier-*', GLOB_ONLYDIR );
		$paquet   = new self();
		foreach ( (array) $ateliers as $atelier ) {
			if ( is_dir( $atelier ) && filemtime( $atelier ) < time() - (int) $age ) {
				$paquet->effacer_le_dossier( $atelier );
			}
		}
	}

	/** Efface le dossier de travail une fois l'archive servie. */
	public function nettoyer() {
		if ( '' !== $this->atelier && is_dir( $this->atelier ) ) {
			$this->effacer_le_dossier( $this->atelier );
		}
		$this->atelier = '';
	}

	private function effacer_le_dossier( $dossier ) {
		$entrees = @scandir( $dossier );
		if ( false === $entrees ) {
			return;
		}
		foreach ( $entrees as $e ) {
			if ( '.' === $e || '..' === $e ) {
				continue;
			}
			$chemin = $dossier . '/' . $e;
			if ( is_dir( $chemin ) ) {
				$this->effacer_le_dossier( $chemin );
			} else {
				@unlink( $chemin );
			}
		}
		@rmdir( $dossier );
	}

	/**
	 * Ce que le paquet contient, et ce qu'il n'a pas pu faire.
	 *
	 * L'export sur sDrive range toutes les rubriques d'un numéro dans un même
	 * dossier : son lisez-moi le dit, et nomme les fichiers de la rubrique.
	 */
	private function lisez_moi( $export = false ) {
		$lignes = array(
			'Paquet de la rubrique : ' . $this->rubrique,
			( $export ? 'Exporté' : 'Assemblé' ) . ' le ' . date_i18n( 'j F Y à H:i' ),
			'',
			(int) $this->comptes['notices'] . ' notice(s), '
				. (int) $this->comptes['illustrations'] . ' illustration(s).',
			'',
		);
		if ( $export ) {
			$lignes = array_merge( $lignes, array(
				'Les rubriques du numéro partagent ce dossier ; celle-ci y a :',
				'',
				'style/' . $this->nom_paquet . '.docx',
				'                 le document Word de la rubrique, aux styles Métopes',
				'XML/indexation/  les blocs d\'index Pactols, un fichier par notice.',
				'                 Chaque « zone » nomme le style du paragraphe où',
				'                 son contenu se colle. Les concepts viennent des',
				'                 identifiants choisis au dépôt : rien n\'a été deviné.',
				'icono/hr/        la haute définition, pour la mise en page',
				'icono/br/        la basse définition, appelée en lien par le document',
				'relecture_' . $this->nom_paquet . '.html',
				'                 la rubrique à lire dans un navigateur, figures comprises',
				'',
				'Un nouvel export de la rubrique remplace ces fichiers, et eux seuls.',
				'originaux/       les fichiers tels que les auteurs les ont envoyés,',
				'                 version par version : l\'export n\'y touche jamais.',
				'                 Les autres dossiers (indd/, le reste de XML/) non plus.',
			) );
		} else {
			$lignes = array_merge( $lignes, array(
				'style/           le document Word de la rubrique, aux styles Métopes',
				'XML/             vide : c\'est la chaîne qui le remplira',
				'XML/indexation/  les blocs d\'index Pactols, un fichier par notice.',
				'                 Chaque « zone » nomme le style du paragraphe où',
				'                 son contenu se colle. Les concepts viennent des',
				'                 identifiants choisis au dépôt : rien n\'a été deviné.',
				'icono/hr/        la haute définition, pour la mise en page',
				'icono/br/        la basse définition, appelée en lien par le document',
				'relecture.html   la rubrique à lire dans un navigateur, figures comprises',
			) );
			if ( $this->avec_originaux ) {
				$lignes[] = 'icono/originaux/ les fichiers tels que les auteurs les ont envoyés';
			}
		}
		$lignes[] = '';
		$ennuis = array_merge( $this->journal, $this->erreurs );
		if ( empty( $ennuis ) ) {
			// « Toutes les illustrations ont été traitées » quand il n'y en a
			// aucune se lit comme une réussite : on cherche alors pourquoi le
			// dossier est vide, au lieu de savoir qu'il n'y avait rien.
			$lignes[] = ( 0 === (int) $this->comptes['illustrations'] )
				? ( $export ? 'Aucune illustration dans cette rubrique, et c’est normal.'
					: 'Aucune illustration dans cette rubrique : les dossiers icono sont donc vides, et c’est normal.' )
				: 'Toutes les illustrations ont été traitées.';
		} else {
			// Zéro illustration posée n'est « normal » que s'il n'y avait rien
			// à poser. Des légendes sans fichier, un fichier illisible : le
			// dossier est vide pour une raison, et elle doit se lire — le
			// lisez-moi disait « c'est normal » et taisait justement celle-là.
			if ( 0 === (int) $this->comptes['illustrations'] ) {
				$lignes[] = 'Aucune illustration n’a pu être posée dans les dossiers icono.';
			}
			$lignes[] = 'Ce qui n\'a pas pu être fait :';
			foreach ( $ennuis as $ligne ) {
				$lignes[] = '  - ' . $ligne;
			}
		}
		// Les avis sur le reste du dossier, qu'il y ait des illustrations ou
		// non.
		if ( ! empty( $this->notes ) ) {
			$lignes[] = '';
			$lignes[] = 'À savoir aussi :';
			foreach ( $this->notes as $ligne ) {
				$lignes[] = '  - ' . $ligne;
			}
		}
		return implode( "\r\n", $lignes );
	}

	/** Le lisez-moi de l'export sur sDrive. */
	public function lisez_moi_de_l_export() {
		return $this->lisez_moi( true );
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

	/** Imagick est-elle là, et sait-elle lire ce format ? */
	private static function imagick_sait( $format ) {
		if ( ! self::imagick_disponible() ) {
			return false;
		}
		$connus = (array) Imagick::queryFormats( strtoupper( $format ) );
		return ! empty( $connus );
	}

	/**
	 * Ce qu'une image d'écran demande et qu'une haute définition ne veut
	 * pas : les couleurs d'écran et le bon sens de lecture.
	 *
	 * Un TIFF en CMJN donnait une basse définition en CMJN, aux couleurs
	 * fausses dans un navigateur et dans Word. Une photo de téléphone porte
	 * son orientation dans ses métadonnées, que la chaîne ignore : elle
	 * paraissait couchée. On fait tourner les pixels. La haute définition,
	 * elle, reste telle que l'auteur l'a donnée : c'est un choix d'imprimeur.
	 */
	private static function pour_l_ecran( $image ) {
		self::vers_le_srgb( $image );
		try {
			switch ( $image->getImageOrientation() ) {
				case Imagick::ORIENTATION_BOTTOMRIGHT:
					$image->rotateImage( '#ffffff', 180 );
					break;
				case Imagick::ORIENTATION_RIGHTTOP:
					$image->rotateImage( '#ffffff', 90 );
					break;
				case Imagick::ORIENTATION_LEFTBOTTOM:
					$image->rotateImage( '#ffffff', 270 );
					break;
			}
			$image->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
		} catch ( Exception $e ) {
			// Une image qu'on ne sait pas redresser part telle quelle : mieux
			// vaut une figure couchée qu'une figure absente.
			unset( $e );
		}
	}

	/**
	 * Ramène au sRGB les couleurs d'une image d'écran, profil compris.
	 *
	 * Une figure préparée pour l'imprimeur porte souvent son profil ICC :
	 * FOGRA39 pour un CMJN, Adobe RGB pour une photo. Le passage au sRGB
	 * « à la formule », sans le lire, ternissait les couleurs et dessinait un
	 * papier beige en vert : l'auteur ne reconnaissait pas sa figure. Avec le
	 * profil, c'est le moteur de couleur d'ImageMagick qui convertit, comme
	 * Photoshop le ferait. L'intention est perceptive : sur FOGRA39, elle ne
	 * s'écarte pas d'un niveau sur deux cent cinquante-cinq de celle de
	 * Photoshop, alors que la colorimétrie relative seule grisait les noirs.
	 *
	 * Si la conversion échoue — un ImageMagick sans moteur de couleur, un
	 * profil abîmé —, un CMJN passe encore au sRGB par la formule, et perd
	 * le profil qui ne le décrit plus.
	 */
	private static function vers_le_srgb( $image ) {
		try {
			$cmjn    = Imagick::COLORSPACE_CMYK === $image->getImageColorspace();
			$profils = $image->getImageProfiles( 'icc', true );
			$icc     = isset( $profils['icc'] ) ? (string) $profils['icc'] : '';
			$autre   = ! $cmjn && Imagick::COLORSPACE_GRAY !== $image->getImageColorspace()
				&& false === stripos( self::description_icc( $icc ), 'srgb' );
			if ( '' !== $icc && ( $cmjn || $autre ) ) {
				$srgb = @file_get_contents( __DIR__ . '/assets/srgb.icc' );
				if ( is_string( $srgb ) && '' !== $srgb ) {
					try {
						$image->setImageRenderingIntent( Imagick::RENDERINGINTENT_PERCEPTUAL );
						$image->profileImage( 'icc', $srgb );
					} catch ( Exception $e ) {
						unset( $e );
					}
				}
			}
			if ( Imagick::COLORSPACE_CMYK === $image->getImageColorspace() ) {
				$image->transformImageColorspace( Imagick::COLORSPACE_SRGB );
				if ( '' !== $icc ) {
					$image->removeImageProfile( 'icc' );
				}
			}
		} catch ( Exception $e ) {
			// Une couleur approchée vaut mieux qu'un aperçu manquant.
			unset( $e );
		}
	}

	/**
	 * Le nom qu'un profil ICC se donne : « sRGB IEC61966-2.1 », « Coated
	 * FOGRA39 »… Vide si on ne le trouve pas.
	 *
	 * C'est à lui qu'on reconnaît un profil sRGB, qu'il est inutile de
	 * convertir. Le nom se lit dans l'étiquette « desc », en ASCII dans les
	 * profils de version 2, en UTF-16 dans ceux de version 4.
	 */
	public static function description_icc( $icc ) {
		$icc    = (string) $icc;
		$taille = strlen( $icc );
		if ( $taille < 132 ) {
			return '';
		}
		$nombre = unpack( 'N', substr( $icc, 128, 4 ) );
		$nombre = min( 100, (int) $nombre[1] );
		for ( $i = 0; $i < $nombre && 132 + 12 * ( $i + 1 ) <= $taille; $i++ ) {
			$entree = unpack( 'a4sig/Noff', substr( $icc, 132 + 12 * $i, 8 ) );
			if ( 'desc' !== $entree['sig'] ) {
				continue;
			}
			$debut = (int) $entree['off'];
			if ( $debut < 0 || $debut + 28 > $taille ) {
				return '';
			}
			$type = substr( $icc, $debut, 4 );
			if ( 'desc' === $type ) {
				$longueur = unpack( 'N', substr( $icc, $debut + 8, 4 ) );
				return rtrim( (string) substr( $icc, $debut + 12, min( 256, (int) $longueur[1] ) ), "\0" );
			}
			if ( 'mluc' === $type ) {
				$texte = unpack( 'Nlongueur/Noff', substr( $icc, $debut + 20, 8 ) );
				return str_replace( "\0", '', (string) substr( $icc, $debut + (int) $texte['off'], min( 512, (int) $texte['longueur'] ) ) );
			}
			return '';
		}
		return '';
	}

	/**
	 * Le serveur sait-il lire un TIFF et un PDF ? Oui s'il a Imagick.
	 *
	 * « NA_SANS_IMAGICK », posée à vrai dans wp-config.php d'un site d'essai,
	 * fait comme s'il manquait : c'est ainsi qu'on éprouve ce que le plugin
	 * fait sans lui — la conversion des TIFF par le navigateur — sur un
	 * serveur qui l'a.
	 */
	public static function imagick_disponible() {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' )
			&& ! ( defined( 'NA_SANS_IMAGICK' ) && NA_SANS_IMAGICK );
	}

	/**
	 * La résolution à laquelle dessiner un PDF.
	 *
	 * Le dessin d'une page coûte en proportion de ses pixels. Pour l'écran,
	 * on la dessine juste assez grande — un quart de plus que la mesure
	 * voulue, que la réduction affine — d'après sa taille déclarée, jamais
	 * au-delà de 150 ppp. Pour la haute définition, à la densité réglée,
	 * jamais au-delà de 600.
	 *
	 * Dans les deux cas, la page dessinée ne dépasse pas MEGAPIXELS_PDF : une
	 * planche qui se déclarait de 87 cm sur 62 — la taille en pixels d'une A4
	 * inscrite comme une taille en points — se dessinait à 600 ppp en trois
	 * cents millions de pixels, et le serveur coupait l'export sur elle.
	 *
	 * La taille est celle de la plus grande page déclarée : prise trop
	 * grande, la page est dessinée un peu petite, et la lecture la reprend
	 * ensuite à sa vraie mesure (voir « reduire »).
	 */
	private static function rendu_du_pdf( $source, $largeur, $dpi, $ecran ) {
		$page = self::plus_grande_page_pdf( $source );
		if ( $ecran ) {
			$rendu = 150;
			if ( null !== $page && $largeur > 0 && PHP_INT_MAX !== (int) $largeur ) {
				$rendu = (int) min( 150, max( 10, ceil( 1.25 * $largeur * 72 / $page['cote'] ) ) );
			}
		} else {
			$rendu = (int) min( 600, max( 150, (int) $dpi ) );
		}
		if ( null !== $page ) {
			$rendu = min( $rendu, self::plafond_du_pdf( $page['aire'] ) );
		}
		return $rendu;
	}

	/** La résolution au-delà de laquelle une page de tant de points carrés passerait MEGAPIXELS_PDF. */
	private static function plafond_du_pdf( $aire ) {
		if ( $aire <= 0 ) {
			return 600;
		}
		return (int) max( 10, floor( 72 * sqrt( self::MEGAPIXELS_PDF * 1000000 / $aire ) ) );
	}

	/**
	 * La plus grande page qu'un PDF déclare (« /MediaBox ») : son grand côté
	 * et sa surface, en points ; null s'il ne s'en lit aucune.
	 *
	 * Un PDF récent range parfois ses pages dans des flux compressés, où
	 * rien ne se lit : on s'en tient alors aux résolutions d'usage, et la
	 * mesure de l'image lue rattrape ce qui doit l'être.
	 *
	 * Le fichier se lit par tranches, qui se recouvrent pour qu'une
	 * déclaration à cheval sur deux ne se perde pas.
	 */
	private static function plus_grande_page_pdf( $source ) {
		$flux = @fopen( $source, 'rb' );
		if ( ! $flux ) {
			return null;
		}
		$cote  = 0.0;
		$aire  = 0.0;
		$reste = '';
		while ( ! feof( $flux ) ) {
			$tranche = fread( $flux, 1048576 );
			if ( false === $tranche || '' === $tranche ) {
				break;
			}
			$texte = $reste . $tranche;
			if ( preg_match_all( '#/MediaBox\s*\[\s*(-?[0-9.]+)\s+(-?[0-9.]+)\s+(-?[0-9.]+)\s+(-?[0-9.]+)\s*\]#', $texte, $boites, PREG_SET_ORDER ) ) {
				foreach ( $boites as $b ) {
					$l = abs( (float) $b[3] - (float) $b[1] );
					$h = abs( (float) $b[4] - (float) $b[2] );
					if ( $l < 1 || $h < 1 ) {
						continue;
					}
					$cote = max( $cote, $l, $h );
					$aire = max( $aire, $l * $h );
				}
			}
			$reste = substr( $texte, -200 );
		}
		fclose( $flux );
		if ( $cote <= 0 && self::imagick_disponible() ) {
			// Rien de lisible : Imagick mesure la première page sans la
			// dessiner en grand (72 ppp, un pixel par point), pour que le
			// plafond de pixels tienne quand même.
			try {
				$sonde = new Imagick();
				$sonde->pingImage( $source . '[0]' );
				$l = (float) $sonde->getImageWidth();
				$h = (float) $sonde->getImageHeight();
				$sonde->clear();
				if ( $l >= 1 && $h >= 1 ) {
					$cote = max( $l, $h );
					$aire = $l * $h;
				}
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}
		return $cote > 0 ? array( 'cote' => $cote, 'aire' => $aire ) : null;
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
	private function reduire( $source, $cible, $largeur, $qualite, $dpi, $poids_max, $tolerance, $ecran = false ) {
		if ( ! self::imagick_disponible() ) {
			return $this->reduire_par_gd( $source, $cible, $largeur, $qualite, $poids_max, $dpi );
		}
		$plancher = ( self::SANS_PLAFOND === $poids_max )
			? 0 : (int) round( $poids_max * ( 100 - $tolerance ) / 100 );
		$image = null;
		try {
			// Un fichier d'inconnu ne doit pas pouvoir épuiser le serveur : on
			// refuse ce qui se déclare gigantesque avant de le lire. Les PDF
			// n'ont pas de dimension à lire d'avance : leur résolution est déjà
			// plafonnée. On ne pose pas de limite de temps ni de mémoire à
			// Imagick : elles valent pour le processus entier, non pour une
			// image, et un processus qui dure les épuise pour les suivantes.
			$dimensions = @getimagesize( $source );
			if ( is_array( $dimensions ) && (int) $dimensions[0] * (int) $dimensions[1] > self::MEGAPIXELS_MAX * 1000000 ) {
				$this->erreurs[] = basename( $source ) . ' : image trop grande (plus de ' . self::MEGAPIXELS_MAX . ' millions de pixels), aperçu non fabriqué.';
				return false;
			}
			$image = new Imagick();
			// Un PDF se dessine à la résolution qu'on lui donne avant de le
			// lire : sans elle, Imagick le rend à 72 ppp, et un plan au trait
			// devenait une image floue étiquetée 1 200. Plafonnée à 600 : une
			// page A4 à 1 200 ppp tiendrait un demi-gigaoctet en mémoire.
			$est_pdf = 'pdf' === strtolower( pathinfo( $source, PATHINFO_EXTENSION ) );
			$rendu   = 0;
			if ( $est_pdf ) {
				$rendu = self::rendu_du_pdf( $source, $largeur, $dpi, $ecran );
				$image->setResolution( $rendu, $rendu );
				$voulu = (int) min( 600, max( 150, (int) $dpi ) );
			}
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
			// La page lue n'atteint pas la mesure voulue : la taille trouvée
			// dans le fichier n'était pas la sienne, mais celle d'une autre
			// page, plus grande. La page lue donne maintenant la vraie : on la
			// relit à la résolution qu'il lui faut, sans dépasser 150 ppp ni le
			// plafond de pixels, plutôt que d'agrandir une image trop petite.
			$relu = 0;
			if ( $est_pdf && $ecran && $rendu < 150 && $rendu > 0
				&& max( $image->getImageWidth(), $image->getImageHeight() ) < $largeur ) {
				$cote_lu = max( $image->getImageWidth(), $image->getImageHeight() ) * 72 / $rendu;
				$aire_lue = $image->getImageWidth() * $image->getImageHeight() * ( 72 / $rendu ) * ( 72 / $rendu );
				$relu = (int) min( 150, ceil( 1.25 * $largeur * 72 / max( 1, $cote_lu ) ), self::plafond_du_pdf( $aire_lue ) );
			}
			if ( $relu > $rendu ) {
				$image->clear();
				$image->setResolution( $relu, $relu );
				try {
					$image->readImage( $source . '[0]' );
				} catch ( Exception $e ) {
					$image->clear();
					$image->readImage( $source );
				}
			}
			// Une grande page dessinée sous la résolution voulue n'a rien perdu
			// tant que ses pixels couvrent encore une pleine page de la revue à
			// cette résolution : un plan de 86 × 62 cm donne 7 000 pixels à
			// 207 ppp. Ce n'est alors qu'une information, et non un échec à
			// reprendre : sept avis de ce genre noyaient ceux qui comptent.
			if ( $est_pdf && ! $ecran && $rendu < $voulu ) {
				$pixels = $image->getImageWidth() * $image->getImageHeight();
				$besoin = self::PLEINE_PAGE_CM[0] * self::PLEINE_PAGE_CM[1] * pow( $voulu / 2.54, 2 );
				if ( $pixels >= $besoin ) {
					$this->notes[] = basename( $cible ) . ' : PDF de grand format dessiné à ' . $rendu . ' ppp au lieu de ' . $voulu
						. ' (' . self::MEGAPIXELS_PDF . ' millions de pixels au plus) ; c’est encore assez pour une pleine page à '
						. $voulu . ' ppp, et l’original se garde parmi les originaux.';
				} else {
					$this->journal[] = basename( $cible ) . ' : PDF dessiné à ' . $rendu . ' ppp au lieu de ' . $voulu
						. ', sa page se déclarant trop grande (plus de ' . self::MEGAPIXELS_PDF
						. ' millions de pixels à cette résolution) ; reprenez au besoin l’original.';
				}
			}
			// Ce qui reste de calques dans cette vue s'aplatit. L'objet
			// d'origine se libère : sur une centaine d'illustrations, les
			// abandonner l'un après l'autre finissait par épuiser la mémoire.
			// « flattenImages » a disparu d'ImageMagick 7 : on retombe alors sur
			// « mergeImageLayers », qui fait la même chose.
			$plat = method_exists( $image, 'flattenImages' )
				? $image->flattenImages()
				: $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
			if ( $plat instanceof Imagick ) {
				$image->clear();
				$image->destroy();
				$image = $plat;
			}
			if ( $ecran ) {
				self::pour_l_ecran( $image );
			}
			$image->setImageFormat( 'jpeg' );
			// Le côté le plus long, hauteur ou largeur, ne dépasse pas la mesure
			// demandée : une figure en hauteur ne dépasse plus de moitié en
			// hauteur ce qu'une figure en largeur ne dépasse pas en largeur.
			$largeur_image = $image->getImageWidth();
			$hauteur_image = $image->getImageHeight();
			if ( max( $largeur_image, $hauteur_image ) > $largeur ) {
				if ( $largeur_image >= $hauteur_image ) {
					$image->resizeImage( $largeur, 0, Imagick::FILTER_LANCZOS, 1 );
				} else {
					$image->resizeImage( 0, $largeur, Imagick::FILTER_LANCZOS, 1 );
				}
			}
			// La résolution s'inscrit, elle ne se fabrique pas : porter une
			// image à 1200 dpi en inventant des pixels l'abîmerait sans rien
			// apporter. On enregistre la densité voulue, et c'est elle que la
			// mise en page lira pour savoir à quelle taille poser la figure.
			if ( $dpi > 0 ) {
				$image->setImageUnits( Imagick::RESOLUTION_PIXELSPERINCH );
				$image->setImageResolution( $dpi, $dpi );
			}

			// On part du haut et l'on descend : le premier palier qui tient
			// sous le plafond est le meilleur que le plafond admette.
			$qualite = max( self::QUALITE_PLANCHER, (int) $qualite );
			for ( $essai = 0; $essai < 10; $essai++ ) {
				$image->setImageCompressionQuality( $qualite );
				$ko = (int) round( strlen( $image->getImageBlob() ) / 1024 );
				if ( $ko <= $poids_max ) {
					break;   // dans la fourchette, ou sans plafond
				}
				if ( $qualite <= self::QUALITE_PLANCHER ) {
					break;   // on ne descend pas plus bas
				}
				$qualite = max( self::QUALITE_PLANCHER, $qualite - self::QUALITE_PALIER );
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
		} catch ( Throwable $e ) {
			// Pas seulement « Exception » : une « Error » (méthode absente,
			// mémoire) n'est pas une Exception, et remontait jusqu'à l'auteur.
			if ( $image instanceof Imagick ) {
				$image->clear();
			}
			$this->erreurs[] = basename( $source ) . ' : ' . $e->getMessage();
			// Une figure absente du document vaut moins qu'une figure moins bien
			// réduite : GD prend le relais pour un JPEG ou un PNG.
			return $this->reduire_par_gd( $source, $cible, $largeur, $qualite, $poids_max, $dpi );
		}
	}

	/**
	 * Inscrit la densité (points par pouce) dans l'en-tête JFIF d'un JPEG.
	 * GD ne l'écrit pas : l'image sortait « sans résolution », que la mise en
	 * page lisait comme 72 ppp et posait deux à quatre fois trop grande.
	 */
	private static function poser_la_densite_du_jpeg( $fichier, $dpi ) {
		$dpi = (int) $dpi;
		if ( $dpi < 1 || $dpi > 65535 || ! is_writable( $fichier ) ) {
			return;
		}
		$octets = @file_get_contents( $fichier );
		// SOI (2 octets), marqueur APP0 (2), longueur (2), « JFIF\0 » (5), version (2) : l'unité est à 13.
		if ( ! is_string( $octets ) || strlen( $octets ) < 20 || "\xFF\xD8\xFF\xE0" !== substr( $octets, 0, 4 ) || 'JFIF' !== substr( $octets, 6, 4 ) ) {
			return;
		}
		$octets = substr( $octets, 0, 13 ) . chr( 1 ) . pack( 'nn', $dpi, $dpi ) . substr( $octets, 18 );
		@file_put_contents( $fichier, $octets );
	}

	/**
	 * GD tiendra-t-il cette image en mémoire ? Il la déplie entière, quatre
	 * octets par pixel, et le décodage en demande un peu plus : cinq en tout,
	 * et une marge, face à ce qui reste sous la limite relevée pour les
	 * images. Au-delà, le processus tomberait sans rien noter, la file
	 * reprendrait la notice et tomberait encore : on renonce avant.
	 */
	public static function memoire_suffit( $largeur, $hauteur ) {
		wp_raise_memory_limit( 'image' );
		$limite = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limite <= 0 ) {
			return true;
		}
		return memory_get_usage() + (int) $largeur * (int) $hauteur * 5 + 16 * MB_IN_BYTES <= $limite;
	}

	/**
	 * Le repli quand Imagick manque ou échoue : GD, pour un JPEG ou un PNG.
	 * Un TIFF ou un PDF ne se lit pas ainsi ; la figure part alors sans
	 * aperçu, comme avant.
	 *
	 * Rend vrai si la cible a été écrite.
	 */
	private function reduire_par_gd( $source, $cible, $largeur, $qualite, $poids_max, $dpi = 0 ) {
		$dimensions = @getimagesize( $source );
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! is_array( $dimensions )
			|| (int) $dimensions[0] * (int) $dimensions[1] > self::MEGAPIXELS_MAX * 1000000 ) {
			return false;
		}
		$lecteurs = array( IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng' );
		if ( ! isset( $lecteurs[ $dimensions[2] ] ) || ! function_exists( $lecteurs[ $dimensions[2] ] ) ) {
			return false;
		}
		// Un JPEG qu'il n'y a ni à rétrécir ni à alléger part tel quel : le
		// recompresser ne ferait que l'abîmer, et il garde ainsi sa densité et
		// ses métadonnées. C'est le cas de la haute définition d'une photographie.
		if ( IMAGETYPE_JPEG === $dimensions[2] && max( $dimensions[0], $dimensions[1] ) <= $largeur && self::SANS_PLAFOND === $poids_max ) {
			return @copy( $source, $cible ) && file_exists( $cible );
		}
		if ( ! self::memoire_suffit( $dimensions[0], $dimensions[1] ) ) {
			$this->erreurs[] = basename( $source ) . ' : image trop grande pour la mémoire du serveur sans Imagick ('
				. number_format_i18n( $dimensions[0] * $dimensions[1] / 1000000, 1 ) . ' millions de pixels), aperçu non fabriqué.';
			return false;
		}
		try {
			$image = @call_user_func( $lecteurs[ $dimensions[2] ], $source );
			if ( ! $image ) {
				return false;
			}
			if ( max( $dimensions[0], $dimensions[1] ) > $largeur ) {
				$reduite = $dimensions[0] >= $dimensions[1]
					? imagescale( $image, (int) $largeur )
					: imagescale( $image, max( 1, (int) round( $dimensions[0] * $largeur / $dimensions[1] ) ), (int) $largeur );
				if ( $reduite ) {
					imagedestroy( $image );
					$image = $reduite;
				}
			}
			// Un PNG transparent se pose sur du blanc : le JPEG n'a pas d'alpha.
			$fond = imagecreatetruecolor( imagesx( $image ), imagesy( $image ) );
			imagefill( $fond, 0, 0, imagecolorallocate( $fond, 255, 255, 255 ) );
			imagecopy( $fond, $image, 0, 0, 0, 0, imagesx( $image ), imagesy( $image ) );
			imagedestroy( $image );
			$qualite = max( self::QUALITE_PLANCHER, (int) $qualite );
			for ( $essai = 0; $essai < 10; $essai++ ) {
				imagejpeg( $fond, $cible, $qualite );
				clearstatcache( true, $cible );
				if ( self::SANS_PLAFOND === $poids_max || filesize( $cible ) / 1024 <= $poids_max
					|| $qualite <= self::QUALITE_PLANCHER ) {
					break;
				}
				$qualite = max( self::QUALITE_PLANCHER, $qualite - self::QUALITE_PALIER );
			}
			imagedestroy( $fond );
			self::poser_la_densite_du_jpeg( $cible, $dpi );
			return file_exists( $cible );
		} catch ( Throwable $e ) {
			$this->erreurs[] = basename( $source ) . ' : ' . $e->getMessage();
			return false;
		}
	}

	/**
	 * Pose dans le dossier un fichier d'indexation, à côté du XML.
	 *
	 * Rend le nom retenu, ou une chaîne vide si l'atelier n'est pas ouvert.
	 */
	public function poser_une_indexation( $nom_sans_ext, $xml ) {
		if ( '' === $this->atelier || ! is_dir( $this->atelier ) ) {
			return '';
		}
		$dossier = $this->atelier . '/XML/indexation';
		if ( ! is_dir( $dossier ) ) {
			wp_mkdir_p( $dossier );
		}
		$libre = self::nom_libre( $nom_sans_ext, $this->noms_indexation );
		if ( $libre !== $nom_sans_ext ) {
			$this->notes[] = $nom_sans_ext . '.xml : deux notices de cette rubrique'
				. ' portent la même commune et le même lieu-dit ; la suivante est posée'
				. ' sous « ' . $libre . ' ». Les fichiers sont bons, mais rien'
				. ' dans leur nom ne dit à quelle notice ils vont : ouvrez-les pour'
				. ' les distinguer.';
		}
		$nom = $libre . '.xml';
		return ( false !== @file_put_contents( $dossier . '/' . $nom, $xml ) ) ? $nom : '';
	}

	/**
	 * Fabrique l'aperçu qu'un document emportera avec lui, et rend sa mesure.
	 *
	 * Séparé des deux définitions du dossier, et volontairement : celles-là
	 * obéissent aux réglages de la rédaction, qui les veut au format et au
	 * poids de la mise en page. Celui-ci n'a qu'un devoir, tenir dans un
	 * courriel. Les lier aurait fait qu'un réglage de mise en page décide du
	 * poids d'une pièce jointe.
	 *
	 * Rend null si l'image n'a pas pu être lue : le document se fabrique
	 * quand même, sans cette figure.
	 */
	public function apercu_du_document( $source, $cible ) {
		$fait = $this->reduire( $source, $cible, self::LARGEUR_APERCU,
			self::QUALITE_HAUTE, 96, self::POIDS_APERCU, 10, true );
		if ( ! $fait ) {
			return null;
		}
		$mesure = @getimagesize( $cible );
		if ( false === $mesure ) {
			return null;
		}
		return array(
			'apercu'  => $cible,
			'largeur' => (int) $mesure[0],
			'hauteur' => (int) $mesure[1],
			'dpi'     => 96,
		);
	}

	/**
	 * Écrit les deux définitions d'une illustration, et rend leurs noms.
	 *
	 * « originaux » garde le fichier tel que l'auteur l'a envoyé. « hr » part
	 * à la mise en page, « br » sert au lien posé dans le document.
	 */
	private function poser_une_illustration( $source, $nom_sans_ext, $conversion = array() ) {
		$ext     = strtolower( pathinfo( $source, PATHINFO_EXTENSION ) );
		$photo   = in_array( $ext, self::MATRICIELS, true );
		$reglage = function ( $clef ) {
			return Notice_Archeomed_Settings::get( $clef );
		};

		if ( $this->avec_originaux ) {
			@copy( $source, $this->atelier . '/icono/originaux/' . $nom_sans_ext . '.' . $ext );
		}

		// — La haute définition —
		$famille = $photo ? 'photo' : 'trait';
		$en_jpeg = (int) $reglage( 'hr_' . $famille . '_jpeg' );
		$fait_hr = false;
		// Sans Imagick, un TIFF a été converti dans le navigateur de l'auteur :
		// les deux JPEG, la haute et la basse définition, voyagent avec la notice.
		// Avec Imagick, le serveur sait faire mieux, et ne s'en sert pas.
		$converti = ! self::imagick_disponible() && ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true );
		$conv_hr  = ( $converti && ! empty( $conversion['hr'] ) && is_readable( $conversion['hr'] ) ) ? $conversion['hr'] : '';
		$conv_br  = ( $converti && ! empty( $conversion['br'] ) && is_readable( $conversion['br'] ) ) ? $conversion['br'] : '';
		// Un TIFF suit le réglage de sa famille ; un PDF, dont l'auteur voulait la
		// haute définition en JPEG pour la mise en page, prend celle du navigateur.
		if ( '' !== $conv_hr && ( $en_jpeg || 'pdf' === $ext ) ) {
			$fait_hr = @copy( $conv_hr, $this->atelier . '/icono/hr/' . $nom_sans_ext . '.jpg' );
		} elseif ( $en_jpeg ) {
			$cible = $this->atelier . '/icono/hr/' . $nom_sans_ext . '.jpg';
			// La haute définition ne se rétrécit pas et n'a pas de plafond :
			// elle part à la mise en page, qui la veut entière.
			$fait_hr = $this->reduire( $source, $cible, PHP_INT_MAX,
				(int) $reglage( 'hr_' . $famille . '_qualite' ),
				(int) $reglage( 'hr_' . $famille . '_dpi' ),
				self::SANS_PLAFOND, 0 );
		}
		if ( ! $fait_hr ) {
			@copy( $source, $this->atelier . '/icono/hr/' . $nom_sans_ext . '.' . $ext );
			if ( $en_jpeg ) {
				$this->journal[] = $nom_sans_ext . '.' . $ext
					. ' : haute définition non convertie (format illisible sur ce serveur), original recopié.';
			}
		}

		// — La basse définition —
		$cible_br = $this->atelier . '/icono/br/' . $nom_sans_ext . '.jpg';
		$dpi_br   = (int) $reglage( 'br_dpi' );
		$fait_br  = ( '' !== $conv_br ) ? @copy( $conv_br, $cible_br ) : $this->reduire( $source, $cible_br,
			(int) $reglage( 'br_largeur' ), self::QUALITE_HAUTE,
			$dpi_br, (int) $reglage( 'br_poids' ), (int) $reglage( 'br_tolerance' ), true );
		if ( ! $fait_br ) {
			$this->journal[] = $nom_sans_ext . '.' . $ext
				. ' : pas de basse définition (Imagick absente, et le fichier n’a pas été converti dans le navigateur de l’auteur, ou son format est illisible). Le document ne posera pas cette figure.';
			return null;
		}

		// La mesure du fichier produit, et non celle qu'on aurait calculée :
		// c'est elle qui dira au document à quelle taille poser la figure.
		$mesure = @getimagesize( $cible_br );
		if ( false === $mesure ) {
			return null;
		}
		return array(
			// Le chemin est donné depuis la racine du paquet. À chacun d'y
			// ajouter ce qu'il faut : le document vit dans « style/ » et
			// remonte d'un cran, la page de relecture est à la racine et
			// n'ajoute rien.
			'fichier' => 'icono/br/' . $nom_sans_ext . '.jpg',
			'largeur' => (int) $mesure[0],
			'hauteur' => (int) $mesure[1],
			'dpi'     => $dpi_br,
		);
	}
}
