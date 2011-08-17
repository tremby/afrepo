<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for a local test set of audio
 *
 * The audio is expected to be in subdirectories of the localtest directory, and 
 * for example purposes can be named in such a way that the example 
 * PathClassifier classifier can get meanining from it.
 */

class AFRepo extends AFRepoBase {
	private $allfiles;

	public function getName() {
		return "Local test audio repository";
	}

	public function getURIPrefix() {
		return "http://localtest.localhost/";
	}

	public function getSparqlEndpoint() {
		return $this->getURIPrefix() . "sparql/";
	}

	public function getDataEndpoint() {
		return $this->getURIPrefix() . "data/";
	}

	public function getAllFiles() {
		if (!is_null($this->allfiles))
			return $this->allfiles;

		$this->allfiles = array();
		$path = dirname(__FILE__) . "/localtest";
		$dir = dir($path);
		while (($file = $dir->read()) !== false) {
			if ($file[0] != "." && is_dir($path . "/" . $file)) {
				$subdir = dir($path . "/" . $file);
				while (($subfile = $subdir->read()) !== false) {
					if ($subfile[0] != "." && is_file($path . "/" . $file . "/" . $subfile))
						$this->allfiles[$path . "/" . $file . "/" . $subfile] = true;
				}
				$subdir->close();
			}
		}
		$dir->close();

		return $this->allfiles;
	}

	public function getSongFiles($id) {
		$filepath = $this->idToLinkPath($id);
		$origfilepath = realpath($filepath);

		if ($origfilepath === false)
			return array();

		// is it a clip?
		if (preg_match('%\.clip\..{1,4}$%', $origfilepath)) {
			// does full version exist?
			$fullpath = preg_replace('%\.clip%', "", $origfilepath);
			if (file_exists($fullpath))
				return array($fullpath, $origfilepath);
			return array($origfilepath);
		}

		// it's a full song. does clip exist?
		$clippath = preg_replace('%(\..{1,4})$%', '.clip\1', $origfilepath);
		if (file_exists($clippath))
			return array($origfilepath, $clippath);
		return array($origfilepath);
	}

	public function haveMetadataPermission() {
		return true;
	}

	public function haveAudioPermission() {
		return ipInRange($_SERVER["REMOTE_ADDR"], "127.0.0.0/8");
	}
}

?>
