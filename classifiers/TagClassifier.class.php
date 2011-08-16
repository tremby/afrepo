<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

class TagClassifier extends AFClassifierBase {
	public function getName() {
		return "Tag";
	}
	public function getDescription() {
		return "Extracts information from any tags present in the audiofile (ID3, Ogg tags, etc)";
	}

	protected function runClassifier($filepath) {
		require_once dirname(dirname(__FILE__)) . "/lib/getid3-1.9.0-20110620/getid3/getid3.php";

		$md = array();

		$getID3 = new getID3();
		$fileinfo = $getID3->analyze($filepath);
		getid3_lib::CopyTagsToComments($fileinfo);
		$md["fromgetid3"] = $fileinfo;

		if (isset($fileinfo["comments"]["title"][0]))
			$md["title"] = $fileinfo["comments"]["title"][0];
		if (isset($fileinfo["comments"]["artist"][0]))
			$md["artist"] = $fileinfo["comments"]["artist"][0];

		if (isset($fileinfo["comments"]["musicbrainz_recordingid"][0])) {
			$md["mbid"] = $fileinfo["comments"]["musicbrainz_recordingid"][0];
			$md["mbid_source"] = "musicbrainz_recordingid tag in audio file";
		} else if (isset($fileinfo["comments"]["musicbrainz_trackid"][0])) {
			$md["mbid"] = $fileinfo["comments"]["musicbrainz_trackid"][0];
			$md["mbid_source"] = "musicbrainz_trackid tag in audio file";
		} else if (isset($fileinfo["id3v2"]["UFID"][0])) {
			$node = $fileinfo["id3v2"]["UFID"][0];
			if (!@empty($node["data"]) && @$node["ownerid"] == "http://musicbrainz.org") {
				$md["mbid"] = $node["data"];
				$md["mbid_source"] = "UFID ID3v2 tag with ownerid==http://musicbrainz.org in audio file";
			}
		}

		// if we don't already have it, look up a Musicbrainz ID if we can
		if ((!isset($md["mbid"]) || empty($md["mbid"])) && isset($md["artist"]) && isset($md["title"])) {
			if ($mbid = musicbrainzLookup($md["artist"], $md["title"])) {
				$md["mbid"] = $mbid;
				$md["mbid_source"] = "Musicbrainz web service lookup for '" . $md["artist"] . "' -- '" . $md["title"] . "'";
			}
		}

		return $md;
	}
}
