<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * PathClassifier
 *
 * This is an example classifier which is supposed to run with the local test 
 * audio repository. It expects subdirectories of the localtest directory, named 
 * by artist name with spaces replaced by underscores. In those artist 
 * directories are audiofiles, named as two-digit track number followed by an 
 * underscore, then the song name with spaces replaced by underscores, then 
 * optionally ".clip", then a filetype extension. For instance:
 *
 *	localtest
 *	|-- aliases
 *	|   `-- 01_we_never_should_have_met.flac
 *	|-- meshuggah
 *	|   |-- 02_new_millennium_cyanide_christ.mp3
 *	|   `-- 06_sane.mp3
 *	|-- metallica
 *	|   |-- 01_battery.clip.mp3
 *	|   |-- 01_battery.ogg
 *	|   `-- 02_master_of_puppets.ogg
 *	|-- pulse_ultra
 *	|   |-- 03_put_it_off.mp3
 *	|   `-- 09_build_your_cages.mp3
 *	|-- rammstein
 *	|   |-- 02_links_2_3_4.mp3
 *	|   |-- 09_rein_raus.clip.mp3
 *	|   `-- 09_rein_raus.mp3
 *	|-- textures
 *	|   |-- 01_old_days_born_anew.ogg
 *	|   `-- 03_awake.ogg
 *	`-- the_livid
 *	    `-- 02_tin_man.ogg
 *
 * This is rather unsophisticated and this is by no means a recommended 
 * audiofile tree convention.
 */

class PathClassifier extends AFClassifierBase {
	public function getName() {
		return "Path";
	}
	public function getDescription() {
		return "Example classifier which extracts information from the file's path, which must be in a particular form";
	}

	public function available() {
		$repo = new AFRepo();
		return $repo->getName() == "Local test audio repository";
	}

	protected function runClassifier($filepath) {
		require_once dirname(dirname(__FILE__)) . "/lib/getid3-1.9.0-20110620/getid3/getid3.php";

		$md = array();

		$canonicalpath = realpath($filepath);
		if (!preg_match('%/([^/]*)/\d\d_([^/]*?)(\.clip)?\..{1,4}$%', $canonicalpath, $matches))
			return false;

		$md["artist"] = str_replace("_", " ", $matches[1]);
		$md["title"] = str_replace("_", " ", $matches[2]);
		$md["clip"] = isset($matches[3]);

		// look up a Musicbrainz ID
		if ($mbid = musicbrainzLookup($md["artist"], $md["title"])) {
			$md["mbid"] = $mbid;
			$md["mbid_source"] = "Musicbrainz web service lookup for '" . $md["artist"] . "' -- '" . $md["title"] . "'";
		}

		return $md;
	}
}
