<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami RWC repository
 */

class AFRepo extends SalamiAFRepoBase {
	public function getName() {
		return "Salami RWC";
	}
	public function getURIPrefix() {
		return "http://rwc.salami.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allSalamiFiles("RWC");
	}
	public function getSparqlEndpoint() {
		return false;
	}
	public function getDataEndpoint() {
		return "http://localhost:7006/data/";
	}
}
