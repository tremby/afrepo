<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Imirsel coversong repository
 */

require_once "AFRepo.Imirsel.class.php";
class AFRepo extends ImirselAFRepoBase {
	public function getName() {
		return "Imirsel coversong";
	}
	public function getURIPrefix() {
		return "http://coversong.imirsel.audiofiles.linkedmusic.org/";
	}
	public function getAllFiles() {
		return $this->allImirselFiles("%/c/%");
	}
}
