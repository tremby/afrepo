<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami IA repository
 */

require_once "AFRepo.Salami.class.php";
class AFRepo extends SalamiAFRepo {
	public function getName() {
		return "Salami IA";
	}
	public function getURIPrefix() {
		return "http://ia.salami.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allSalamiFiles("IA");
	}
}
