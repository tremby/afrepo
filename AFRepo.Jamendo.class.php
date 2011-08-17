<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for the Jamendo repository
 */

class AFRepo extends AFRepoBase {
	private $allfiles;

	public function getName() {
		return "Jamendo";
	}

	public function getURIPrefix() {
		return "http://jamendo.audiofiles.linkedmusic.org/";
	}

	public function getAllFiles() {
		if (!is_null($this->allfiles))
			return $this->allfiles;

		// if cache file exists use it
		$cachefile = "/tmp/jamendo_filelist_cache";
		if (file_exists($cachefile))
			return $this->allfiles = unserialize(file_get_contents($cachefile));

		trigger_error("generating all files list", E_USER_NOTICE);

		// get list of files in dir
		$path = dirname(__FILE__) . "/jamendo";
		$dir = dir($path);
		$this->allfiles = array();
		while (($file = $dir->read()) !== false)
			if ($file[0] != ".")
				$this->allfiles[realpath($path . "/" . $file)] = true;
		$dir->close();

		// write cache file
		file_put_contents($cachefile, serialize($this->allfiles));

		return $this->allfiles;
	}

	public function haveMetadataPermission() {
		return true;
	}

	public function haveAudioPermission() {
		return true;
	}

	protected function extraTriples($id) {
		$files = $this->getSongFiles($id);
		$ids = array_map(array($this, "filePathToId"), $files);

		$classifier = new JamendoClassifier();
		$triples = array();

		// loop through the audiofiles for this tune
		foreach ($ids as $key => $fileid) {
			if ($key === 0) {
				$md = $classifier->classify($fileid);
				if (isset($md["trackid"]))
					$triples[] = "repo:$fileid#DigitalSignal owl:sameAs <http://dbtune.org/jamendo/signal/" . $md["trackid"] . ">";
			}
		}

		return $triples;
	}
}

?>
