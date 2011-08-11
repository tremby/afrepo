<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Imirsel uspop repository
 */

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
}
