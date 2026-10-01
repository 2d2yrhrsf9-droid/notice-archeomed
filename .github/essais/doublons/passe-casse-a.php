<?php
// Une variable « $endif » ne ferme pas le bloc : la classe reste conditionnelle.
IF ( ! class_exists( 'Casse' ) ) :
	$endif = 1;
	class Casse {}
ENDIF;
