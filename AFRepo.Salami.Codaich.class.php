<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami Codaich repository
 */

class AFRepo extends SalamiAFRepoBase {
	public function getName() {
		return "Salami Codaich";
	}
	public function getURIPrefix() {
		return "http://codaich.salami.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allSalamiFiles("Codaich");
	}
}
