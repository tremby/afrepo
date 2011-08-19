<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * Abstract AFRepo extension for the Salami repository
 */

abstract class SalamiAFRepoBase extends AFRepoBase {
	private $allfiles = null;

	protected function allSalamiFiles($col1) {
		if (!is_null($this->allfiles))
			return $this->allfiles;

		$fh = fopen(dirname(__FILE__) . "/salami_metadata.csv", "r");
		if ($fh === false)
			throw new Exception("couldn't open Salami metadata CSV");
		while (($data = fgetcsv($fh, 0, "\t")) !== false)
			if ($data[0] == $col1)
				$this->allfiles[$data[4]] = true;
		fclose($fh);
		return $this->allfiles;
	}

	public function haveMetadataPermission() {
		return true;
	}

	public function haveAudioPermission() {
		return ipInRange($_SERVER["REMOTE_ADDR"], array(
			"127.0.0.0/8", //localhost
			"152.78.64.0/23", //ECS (specifically LSL?)
			"128.174.0.0/16", //UIUC
		));
	}
}

?>
