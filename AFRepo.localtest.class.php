<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepo extension for a local test set of audio
 */

require_once("AFRepoBase.class.php");
class AFRepo extends AFRepoBase {
	private $db;
	private $allfiles;

	public function getName() {
		return "Local test audio repository";
	}

	public function getURIPrefix() {
		return "http://localtest.localhost/";
	}

	public function getAllFiles() {
		if (!is_null($this->allfiles))
			return $this->allfiles;

		$this->allfiles = array();
		$path = dirname(__FILE__) . "/localtest";
		$dir = dir($path);
		while (($file = $dir->read()) !== false)
			if ($file[0] != ".")
				$this->allfiles[$path . "/" . $file] = true;
		$dir->close();

		return $this->allfiles;
	}

	public function getSongFiles($id) {
		$filepath = $this->idToLinkPath($id);
		$origfilepath = realpath($filepath);

		if ($origfilepath === false)
			return array();

		// is it a clip?
		if (preg_match('%_clip\..{1,4}$%', $origfilepath)) {
			// does full version exist?
			$fullpath = preg_replace('%_clip%', "", $origfilepath);
			if (file_exists($fullpath))
				return array($fullpath, $origfilepath);
			return array($origfilepath);
		}

		// it's a full song. does clip exist?
		$clippath = preg_replace('%(\..{1,4})$%', '_clip\1', $origfilepath);
		if (file_exists($clippath))
			return array($origfilepath, $clippath);
		return array($origfilepath);
	}
}

?>