<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Imirsel uspop repository
 */

require_once dirname(__FILE__) . "/ImirselAFRepoBase.class.php";
class AFRepo extends ImirselAFRepoBase {
	public function getName() {
		return "Imirsel uspop";
	}
	public function getURIPrefix() {
		return "http://uspop.imirsel.audiofiles.linkedmusic.org/";
	}
	public function getPathFilter() {
		return "%/b/%";
	}
	public function getDataEndpoint() {
		return "http://localhost:7002/data/";
	}
}
