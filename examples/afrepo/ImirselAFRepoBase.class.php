<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * Abstract AFRepo extension for the Imirsel repository
 */

require_once dirname(__FILE__) . "/SalamiAFRepoBase.class.php";
abstract class ImirselAFRepoBase extends AFRepoBase {
	private $db = null;
	private $allfiles = null;
	private $preferredfiles = null;

	abstract protected function getPathFilter();

	public function getAllFiles() {
		if (!is_null($this->allfiles))
			return $this->allfiles;

		$db = $this->getDB();
		$pathfilter = $this->getPathFilter();

		$result = $db->query("
			SELECT file.path, file.track_id
			FROM file
			JOIN track ON track.id=file.track_id
			JOIN collection_track_link ON track.id=collection_track_link.track_id
			JOIN collection ON collection.name='IMIRSEL' AND collection.id=collection_track_link.collection_id
			" . (!is_null($pathfilter) ? "WHERE file.path LIKE '" . $db->real_escape_string($pathfilter) . "'" : "") . "
		;");
		if ($result === false)
			throw new Exception($db->error);

		$this->allfiles = array();
		while ($row = $result->fetch_assoc())
			$this->allfiles[$row["path"]] = $row["track_id"];

		return $this->allfiles;
	}

	public function getAllPreferredFiles() {
		if (!is_null($this->preferredfiles))
			return $this->preferredfiles;

		$one_file_per_track = array_flip($this->getAllFiles());
		$preferredfiles = array();
		foreach ($one_file_per_track as $filepath)
			$preferredfiles[] = $this->getPreferredFile($this->filePathToId($filepath));

		return $this->preferredfiles = array_flip($preferredfiles);
	}

	public function getSongFiles($id) {
		$filepath = $this->idToLinkPath($id);
		$db = $this->getDB();
		$origfilepath = realpath($filepath);

		// get metadata for each file which has the same imirsel track id is the 
		// given one
		$result = $db->query("
			SELECT f.track_id, f.path, fmdd.name, fmd.value
			FROM file AS f
			JOIN file_file_metadata_link AS ffmd ON f.id=ffmd.file_id
			JOIN file_metadata AS fmd ON fmd.id=ffmd.file_metadata_id
			JOIN file_metadata_definitions AS fmdd ON fmd.metadata_type_id=fmdd.id
			WHERE f.track_id = (
				SELECT track_id
				FROM file
				WHERE path = '" . $db->real_escape_string($origfilepath) . "'
			)
		;");
		$files = array();
		while ($row = $result->fetch_assoc()) {
			if (!array_key_exists($row["path"], $files))
				$files[$row["path"]] = array();
			$files[$row["path"]][$row["name"]] = $row["value"];
		}

		// sort the files so the best is at the top
		uasort($files, array("self", "sort_by_preference"));

		return array_keys($files);
	}

	// score one file against another based on their metadata
	private static function sort_by_preference($a, $b) {
		$balance = 0;
		if (isset($a["encoding"]) && isset($b["encoding"])) {
			$balance -= self::encodingscore($a["encoding"]);
			$balance += self::encodingscore($b["encoding"]);
		}
		if (isset($a["sample-rate"]) && isset($b["sample-rate"])) {
			$balance -= self::sampleratescore($a["sample-rate"]);
			$balance += self::sampleratescore($b["sample-rate"]);
		}
		if (isset($a["channels"]) && isset($b["channels"])) {
			$balance -= self::channelsscore($a["channels"]);
			$balance += self::channelsscore($b["channels"]);
		}
		if (isset($a["bitrate"]) && isset($b["bitrate"])) {
			$balance -= self::bitratescore($a["bitrate"]);
			$balance += self::bitratescore($b["bitrate"]);
		}
		if (isset($a["clip-type"]) && isset($b["clip-type"])) {
			$balance -= self::cliptypescore($a["clip-type"]);
			$balance += self::cliptypescore($b["clip-type"]);
		}
		return $balance;
	}

	// prefer wave or flac to other
	private static function encodingscore($encoding) {
		switch ($encoding) {
			case "wav":
			case "flac":
				return 10;
			default:
				return 0;
		}
	}

	// prefer higher sample rates
	private static function sampleratescore($samplerate) {
		return intval(20 * $samplerate / 44100);
	}

	// prefer stereo over mono
	private static function channelsscore($channels) {
		if ($channels >= 2)
			return 5;
		return 0;
	}

	// prefer higher bitrates
	private static function bitratescore($bitrate) {
		return intval(5 * $bitrate / 128);
	}

	// prefer the whole song
	private static function cliptypescore($cliptype) {
		switch ($cliptype) {
			case "full":
				return 1000;
			default:
				return 0;
		}
	}

	public function getDB() {
		if (!is_null($this->db))
			return $this->db;

		$dbconfig = parse_ini_file(dirname(__FILE__) . "/dbsetup.sh");
		if ($dbconfig === false)
			throw new Exception("couldn't load database configuration");
		$this->db = new mysqli($dbconfig["DBHOST"], $dbconfig["DBUSER"], $dbconfig["DBPASS"], $dbconfig["DBNAME"]);
		$error = mysqli_connect_error();
		if ($error)
			throw new Exception("failed to connect to database: " . $error);
		return $this->db;
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

	public function getMBID($id) {
		$classifiers = array(
			new TagClassifier(),
			new EchonestClassifier(),
			new ImirselDbClassifier(),
		);
		foreach ($classifiers as $classifier)
			if ($classifier->available() && $classifier->hasMBID($id))
				return $classifier->getMBID($id);
		return null;
	}

	public function getSparqlEndpoint() {
		return $this->getURIPrefix() . "sparql/";
	}
}

?>
