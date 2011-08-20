<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami Codaich repository
 */

require_once dirname(__FILE__) . "/SalamiAFRepoBase.class.php";
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
	public function getDataEndpoint() {
		return "http://localhost:7003/data/";
	}
}
