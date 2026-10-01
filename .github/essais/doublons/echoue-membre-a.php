<?php
// « ->if( » suivi d'un deux-points de ternaire n'ouvre aucun bloc : la classe est déclarée sans condition.
$x = $y ? $o->if( 1 ) : 2;
class Doublon_Membre {}
