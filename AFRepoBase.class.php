<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFRepoBase
 *
 * This needs to be extended as AFRepo for the repository being implemented 
 * adding logic for the abstract methods and modifying logic of the other 
 * methods as necessary.
 */

abstract class AFRepoBase {
	/**
	 * getName
	 * Return the name of the audiofile repository
	 */
	abstract public function getName();

	/**
	 * getURIPrefix
	 * Return the repository's URI prefix (with a trailing slash)
	 */
	abstract public function getURIPrefix();

	/**
	 * getAllFiles
	 * Return a backwards array containing canonical pathnames for every file in 
	 * the repository
	 *
	 * A backwards array is something like array("/path/to/file" => true, 
	 * "/path/to/file2" => true), where the keys are the paths and the values 
	 * are ignored. This kind of array is much faster to search.
	 *
	 * If blacklist functionality is required, this method should deal with 
	 * filtering any blacklisted files out.
	 *
	 * Throw an exception if there was a problem.
	 */
	abstract public function getAllFiles();

	/**
	 * inRepo
	 * Return true if the audiofile with the given path (canonical or symlink) 
	 * is in the repository or false if not
	 */
	public function inRepo($filepath) {
		return array_key_exists(realpath($filepath), $this->getAllFiles());
	}

	/**
	 * getSongFiles
	 * Return an array containing all canonical pathnames leading to audiofiles 
	 * of the same song as the audiofile with the given ID, ordered by 
	 * preference
	 *
	 * For instance, given the ID of a 30 second clip of track A 
	 * "0123456789abcdef0123456789abcdef" (which leads to a symlink to 
	 * "/data/tracks/A/clip.mp3"), the method might return
	 *	array(
	 *		"/data/tracks/A/full.flac",
	 *		"/data/tracks/A/full.mp3",
	 *		"/data/tracks/A/clip.flac",
	 *		"/data/tracks/A/clip.mp3",
	 *	)
	 *
	 * Return an empty array if the file was not found (not part of the 
	 * repository).
	 *
	 * The default behaviour is to assume there is only one file for each song 
	 * and this method must be overridden if that is not the case.
	 */
	public function getSongFiles($id) {
		$filepath = $this->idToLinkPath($id);
		if (!$this->inRepo($filepath))
			return array();
		return array(realpath($filepath));
	}

	/**
	 * getPreferredFile
	 * Return the canonical path to the preferred audiofile of the same song as 
	 * the audiofile with the given ID
	 *
	 * This might or might not be the same file as the one the ID represents.
	 * Return false if the given file is not in the repository.
	 */
	public function getPreferredFile($id) {
		$files = $this->getSongFiles($id);
		if (empty($files))
			return false;
		return array_shift($files);
	}

	/**
	 * getPreferredId
	 * Return the ID of the preferred audiofile of the same song as the 
	 * audiofile with the given ID
	 *
	 * This might or might not be the same ID as the one given.
	 * Return false if the file with the given ID is not in the repository.
	 */
	public function getPreferredId($id) {
		$file = $this->getPreferredFile($id);
		if ($file === false)
			return false;
		return $this->filePathToId($file);
	}

	/**
	 * getHostname
	 * Return the hostname of this repository (derived from the URI prefix)
	 */
	public function getHostname() {
		return parse_url($this->getURIPrefix(), PHP_URL_HOST);
	}

	/**
	 * getAudioPath
	 * Return the path to the audio links in this repository (without a trailing 
	 * slash)
	 */
	public function getAudioPath() {
		return dirname(__FILE__) . "/audio";
	}

	/**
	 * splitId
	 * Return the ID with a slash inserted after the second character
	 */
	public static function splitId($id) {
		return substr($id, 0, 2) . "/" . substr($id, 2);
	}

	/**
	 * idToLinkPath
	 * Return the path to the symlink of the audio file with the given ID
	 */
	public function idToLinkPath($id) {
		return $this->getAudioPath() . "/" . self::splitId($id);
	}

	/**
	 * idToCanonicalPath
	 * Return the path to the canonical file with the given ID
	 */
	public function idToCanonicalPath($id) {
		return realpath($this->idToLinkPath($id));
	}

	/**
	 * filePathToId
	 * Return the ID of the audiofile with the given path (which can be 
	 * canonical or a symlink)
	 */
	public function filePathToId($filepath) {
		if (!$this->inRepo($filepath))
			throw new Exception("file with path '$filepath' is not in the repository");
		$id = md5(realpath($filepath));
		return $id;
	}

	/**
	 * filePathToLinkPath
	 * Return the path to the symlink of the given audio file (which could be a 
	 * symlink or canonical)
	 */
	public function filePathToLinkPath($filepath) {
		return $this->idToLinkPath($this->filePathToId($filepath));
	}
}

?>
