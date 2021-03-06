<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

class SalamiClassifier extends AFClassifierBase {
	protected $fh = null;

	public function getName() {
		return "Salami metadata";
	}
	public function getDescription() {
		return "Pulls any metadata available from Salami metadata CSV file";
	}
	public function available() {
		if (!class_exists("SalamiAFRepoBase"))
			return false;
		$repo = new AFRepo();
		return is_a($repo, "SalamiAFRepoBase");
	}

	// return a rewound file handler
	protected function getFH() {
		if (!is_null($this->fh)) {
			rewind($this->fh);
			return $this->fh;
		}

		$this->fh = fopen(dirname(__FILE__) . "/salami_metadata.csv", "r");
		if ($this->fh === null)
			throw new Exception("couldn't open Salami metadata CSV file");

		return $this->fh;
	}

	public function __destruct() {
		if (!is_null($this->fh))
			fclose($this->fh);
	}

	protected function runClassifier($filepath) {
		$fh = $this->getFH();
		$md = array();
		$origfilepath = realpath($filepath);

		// grab metadata from CSV file
		while (($data = fgetcsv($fh, 0, "\t")) !== false) {
			if ($data[4] == $origfilepath) {
				$md["set"] = $data[0];
				$md["subset"] = $data[1];
				$md["artist"] = $data[2];
				$md["title"] = $data[3];
				break;
			}
		}
		if (empty($md))
			throw new Exception("couldn't find path in metadata file");

		// look up Musicbrainz ID
		if ($mbid = musicbrainzLookup($md["artist"], $md["title"])) {
			$md["mbid"] = $mbid;
			$md["mbid_source"] = "Musicbrainz web service lookup for '" . $md["artist"] . "' -- '" . $md["title"] . "'";
		}

		return $md;
	}
}

