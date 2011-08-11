<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami Isophonics repository
 */

class AFRepo extends SalamiAFRepoBase {
	public function getName() {
		return "Salami Isophonics";
	}
	public function getURIPrefix() {
		return "http://isophonics.salami.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allSalamiFiles("Isophonics");
	}
}
