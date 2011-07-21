<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

abstract class AFRepo {
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
	 * getMetadata
	 * Return metadata from the source (database or whatever) for the audiofile 
	 * at the given path and its signal -- that is, any metadata about the given 
	 * signal, plus metadata about the song it encodes.
	 *
	 * Audiofile here means the file itself -- metadata like number of channels, 
	 * sample rate and so on.
	 * Signal here means the song -- metadata like "Artist", "Title".
	 *
	 * $metadata = array(
	 *	"audiofile" => array(
	 *		"field1" => "value1",
	 *		"field2" => "value2",
	 *		...
	 *	),
	 *	"signal" => array(
	 *		"field1" => "value1",
	 *		"field2" => "value2",
	 *	),
	 * )
	 *
	 * It's probably a good idea to dereference the filename in case it is a 
	 * symlink.
	 *
	 * Return false if metadata could not be retrieved (if there was a problem, 
	 * rather than if there is simply no metadata).
	 */
	public function getMetadata($filepath) {
		return array("audiofile" => array(), "signal" => array());
	}


	/**
	 * getAllFiles
	 * Return an array containing all canonical pathnames leading to audiofiles 
	 * of the same song as the given pathname, ordered by preference.
	 *
	 * For instance, given the filename of a 30 second clip of track A 
	 * "symlinks/clip-of-A.mp3" (which is a symlink to "/data/tracks/A/clip.mp3"), 
	 * the method might return
	 *	array(
	 *		"/data/tracks/A/full.flac",
	 *		"/data/tracks/A/full.mp3",
	 *		"/data/tracks/A/clip.flac",
	 *		"/data/tracks/A/clip.mp3",
	 *	)
	 *
	 * Return an empty array if the file was not found (not part of the 
	 * repository) or false if there was a problem.
	 */
	abstract public function getAllFiles($filepath);

	/**
	 * getPreferredFile
	 * Return the canonical path to the preferred audiofile of the same song as 
	 * the given pathname. This might or might not be the same file as was 
	 * given, and if it is the same file it might or might not be the same path 
	 * (the given path may have been a symlink).
	 */
	public function getPreferredFile($filepath) {
		$files = $this->getAllFiles($filepath);
		if ($files === false || empty($files))
			return false;
		return array_shift($files);
	}

	// call ffmpeg to determine track length, no matter what format it is
	// return the length in seconds
	public static function medialength($filepath) {
		if (!file_exists($filepath))
			trigger_error("tried to get length of media file '$filepath' which doesn't exist", E_USER_ERROR);
		$fd = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w"),
		);
		$ffmpeg = proc_open('ffmpeg -i ' . escapeshellarg($filepath), $fd, $pipes);
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$out .= stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$code = proc_close($ffmpeg);

		if ($code != 1)
			trigger_error("ffmpeg exited with code $code (should be 1 since no output file is specified) when trying to determine length of file '$filepath'", E_USER_ERROR);

		$matches = null;
		if (!preg_match('%.*Duration: (..):(..):(..)\.(..).*%', $out, $matches))
			trigger_error("ffmpeg didn't return a duration for file '$filepath'", E_USER_ERROR);

		return floatVal($matches[4] / 100) + intVal($matches[3]) + intVal($matches[2]) * 60 + intVal($matches[1]) * 60 * 60;
	}

	/**
	 * getMusicbrainzID
	 * return the Musicbrainz ID of the given file by checking the fingerprinter 
	 * log
	 */
	public function getMusicbrainzID($filepath) {
		if (!file_exists($filepath))
			trigger_error("tried to get Musicbrainz ID of media file '$filepath' which doesn't exist", E_USER_ERROR);

		$origfilepath = realpath($filepath);

		$fd = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w"),
		);

		$grep = proc_open("grep -e " . escapeshellarg("^" . $origfilepath) . " '" . $this->getFPLogPath() .  "'", $fd, $pipes);
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$out .= stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$code = proc_close($grep);

		if ($code != 0)
			return false;

		$fields = explode("\t", $out);
		$dbmb = trim($fields[7]);
		$fpmb = trim($fields[14]);
		if (!empty($dbmb) && $dbmb == $fpmb)
			return $dbmb;

		return false;
	}

	/**
	 * getFPLogPath
	 * Return the path to the fingerprinter log file associated with this AF 
	 * repo (with no trailing slash)
	 */
	abstract public function getFPLogPath();

	/**
	 * getHostname
	 * Return the hostname of this repository (derived from the URI prefix)
	 */
	public function getHostname() {
		return parse_url($this->getURIPrefix(), PHP_URL_HOST);
	}

	/**
	 * getRepositories
	 * Load all repository classes and return one of each in an array, indexed 
	 * by class name
	 */
	public static function getRepositories() {
		$repositories = array();
		foreach (glob(dirname(__FILE__) . "/repositories/*/*AFRepo.class.php") as $phpfile) {
			require_once $phpfile;
			$classname = preg_replace('%^.*/([^.]*AFRepo)\.class\.php$%', '\1', $phpfile);
			$repositories[$classname] = new $classname;
		}
		return $repositories;
	}

	/**
	 * getRepository
	 * Load the right class file and return the repository by class name, or 
	 * false if it doesn't exist
	 */
	public static function getRepository($classname) {
		$repositories = self::getRepositories();
		if (!array_key_exists($classname, $repositories))
			return false;
		return $repositories[$classname];
	}

	/**
	 * getAudioPath
	 * Return the path to the audio links in this repository
	 */
	abstract public function getAudioPath();
}

?>
