<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami RWC repository
 */

require_once "AFRepo.Salami.class.php";
class AFRepo extends SalamiAFRepo {
	public function getName() {
		return "Salami RWC";
	}
	public function getURIPrefix() {
		return "http://rwc.salami.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allSalamiFiles("RWC");
	}
}
