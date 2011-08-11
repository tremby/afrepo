<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Imirsel coversong repository
 */

class AFRepo extends ImirselAFRepoBase {
	public function getName() {
		return "Imirsel coversong";
	}
	public function getURIPrefix() {
		return "http://coversong.imirsel.audiofiles.linkedmusic.org/";
	}
	public function getPathFilter() {
		return "%/c/%";
	}
}
