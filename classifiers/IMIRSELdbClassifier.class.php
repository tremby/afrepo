<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

class IMIRSELdbClassifier extends AFClassifierBase {
	private $db = null;

	public function getName() {
		return "IMIRSEL database";
	}
	public function getDescription() {
		return "Pulls whatever metadata is available from the IMIRSEL database";
	}

	private function getDB() {
		if (!is_null($this->db))
			return $this->db;

		$repo = new AFRepo();
		return $this->db = $repo->getDB();
	}

	protected function runClassifier($filepath) {
		$db = $this->getDB();
		$md = array();
		$origfilepath = realpath($filepath);

		// get audiofile (database: "file") metadata
		$result = $db->query("
			SELECT f.track_id, fmdd.name, fmd.value
			FROM file AS f
			LEFT JOIN file_file_metadata_link AS ffmd ON f.id=ffmd.file_id
			LEFT JOIN file_metadata AS fmd ON fmd.id=ffmd.file_metadata_id
			LEFT JOIN file_metadata_definitions AS fmdd ON fmd.metadata_type_id=fmdd.id
			WHERE f.path = '" . $db->real_escape_string($origfilepath) . "'
		;");
		$trackid = null;
		while ($row = $result->fetch_assoc()) {
			$trackid = $row["track_id"];
			if (!is_null($row["name"]) && !is_null($row["value"]))
				$md[$row["name"]] = $row["value"];
		}

		// abort if there's no track ID (this will happen if we don't find a 
		// matching row)
		if (is_null($trackid))
			throw new Exception("couldn't find track ID for file '$origfilepath'");

		// get signal (database: "track") metadata
		$result = $db->query("
			SELECT tmdd.name, tmd.value
			FROM track AS t
			JOIN track_track_metadata_link AS ttmd ON t.id=ttmd.track_id
			JOIN track_metadata AS tmd ON tmd.id=ttmd.track_metadata_id
			JOIN track_metadata_definitions AS tmdd ON tmd.metadata_type_id=tmdd.id
			WHERE t.id = '" . $db->real_escape_string($trackid) . "'
		;");
		while ($row = $result->fetch_assoc())
			$md[$row["name"]] = $row["value"];

		// look up Musicbrainz ID
		if (!isset($md["mbid"]) && isset($md["Artist"]) && isset($md["Title"]))
			$md["mbid"] = musicbrainzLookup($md["Artist"], $md["Title"]);

		return $md;
	}
}
