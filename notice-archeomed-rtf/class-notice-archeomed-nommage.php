<?php
/**
 * La règle des noms de fichiers.
 *
 * Pas d'accent, pas d'espace, le souligné pour seul séparateur.
 *
 * Un nom de fichier ne reste pas dans le plugin : il traverse un courriel,
 * une archive zip, Word, la chaîne XML et le disque de qui l'ouvrira. Chacun
 * a sa façon de se tromper — l'accent que macOS décompose et que les
 * expressions régulières ne reconnaissent plus, l'espace qu'une URL
 * transforme en « %20 », le tiret cadratin qu'un script d'InDesign avale. On
 * ne s'en aperçoit qu'au bout de la chaîne, quand l'image ne se pose pas et
 * que plus rien ne dit pourquoi.
 *
 * « Briollay, plan du palais de justice.EPS » devient donc
 * « Briollay_plan_du_palais_de_justice.eps ».
 *
 * La casse est gardée : elle ne gêne personne et « Briollay » se lit mieux
 * que « briollay ». L'extension, elle, passe en bas de casse — « .JPG » et
 * « .jpg » désignent le même format, et deux fichiers qui n'en diffèrent que
 * par là sont un piège sur un disque qui ne distingue pas la casse.
 *
 * La règle est celle de la chaîne éditoriale de la revue, suivie à la lettre :
 * un dossier passé d'un outil à l'autre doit garder ses noms.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Nommage {

	/** Ce qui n'a pas sa place dans un nom : tout sauf lettres, chiffres, souligné. */
	const INDESIRABLE = '/[^A-Za-z0-9_]+/';

	/**
	 * « Légendes » devient « Legendes », que le nom vienne en NFC ou en NFD.
	 *
	 * « remove_accents » de WordPress fait le travail sur la composée ; la
	 * décomposée — celle que rend macOS — lui échappe, et il faut d'abord la
	 * recomposer. Sans « intl » ni « iconv », on s'en remet à WordPress seul :
	 * c'est le cas rare, et mieux vaut un nom imparfait qu'une erreur.
	 */
	public static function sans_accents( $texte ) {
		$texte = (string) $texte;
		if ( class_exists( 'Normalizer' ) ) {
			$compose = Normalizer::normalize( $texte, Normalizer::FORM_C );
			if ( is_string( $compose ) ) {
				$texte = $compose;
			}
		}
		return remove_accents( $texte );
	}

	/**
	 * La tige d'un nom : la règle appliquée à ce qui ne porte pas d'extension.
	 */
	public static function assainir( $tige, $defaut = 'fichier' ) {
		$propre = preg_replace( self::INDESIRABLE, '_', self::sans_accents( $tige ) );
		// Deux séparateurs de suite ne disent rien de plus qu'un seul.
		$propre = preg_replace( '/_{2,}/', '_', (string) $propre );
		$propre = trim( (string) $propre, '_' );
		return '' !== $propre ? $propre : $defaut;
	}

	/**
	 * Un nom bâti sur le modèle des réglages.
	 *
	 * Les jetons — « {commune} », « {n} » — sont remplacés par leur valeur,
	 * puis le tout passe à la règle : c'est elle qui décide, et non ce que
	 * l'on a écrit dans le modèle. Un jeton qu'on n'a pas su remplir
	 * disparaît plutôt que de rester en toutes lettres dans le nom.
	 */
	public static function construire( $modele, $valeurs, $extension = '' ) {
		$nom = (string) $modele;
		foreach ( (array) $valeurs as $jeton => $valeur ) {
			$nom = str_replace( '{' . $jeton . '}', (string) $valeur, $nom );
		}
		$nom = preg_replace( '/\{[a-z_]*\}/', '', $nom );
		$nom = self::assainir( $nom );
		$extension = strtolower( trim( (string) $extension, '.' ) );
		return '' !== $extension ? $nom . '.' . $extension : $nom;
	}

	/**
	 * Le nom complet, extension comprise et mise en bas de casse.
	 *
	 * Un nom qui ne laisserait rien — « ??? .jpg » — reçoit le nom de repli
	 * plutôt qu'un fichier sans nom.
	 */
	public static function nom_de_fichier( $nom, $defaut = 'fichier' ) {
		$nom       = (string) $nom;
		$extension = strtolower( (string) pathinfo( $nom, PATHINFO_EXTENSION ) );
		$tige      = self::assainir( pathinfo( $nom, PATHINFO_FILENAME ), $defaut );
		return '' !== $extension ? $tige . '.' . $extension : $tige;
	}
}
