<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Salami IA repository
 */

class AFRepo extends SalamiAFRepoBase {
	public function getName() {
		return "Salami IA";
	}
	public function getURIPrefix() {
		return "http://ia.salami.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allSalamiFiles("IA");
	}
	public function getSparqlEndpoint() {
		return false;
	}
	public function getDataEndpoint() {
		return "http://localhost:7004/data/";
	}
}
