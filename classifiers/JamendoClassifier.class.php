<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * JamendoClassifier
 *
 * This is a hack and should not be trusted.
 */

class JamendoClassifier extends AFClassifierBase {
	private $dbfile;

	public function getName() {
		return "Jamendo";
	}
	public function getDescription() {
		return "Hackily try to get a MBID by looking at the path name, assuming way too much stuff";
	}

	public function available() {
		$repo = new AFRepo();
		return $repo->getName() == "Jamendo";
	}

	private function getDBFile() {
		if (!is_null($this->dbfile)) {
			rewind($this->dbfile);
			return $this->dbfile;
		}

		$this->dbfile = fopen("/mnt/data/jamendo/albumtrackslist", "r");
		return $this->dbfile;
	}

	protected function runClassifier($filepath) {
		$md = array();

		// pull album id and track number from canonical path
		$canonicalpath = realpath($filepath);
		preg_match('%/(\d+)/(\d+)[^/]*\..{1,4}$%', $canonicalpath, $matches);
		$albumid = intval($matches[1]);
		$tracknum = intval($matches[2]);

		// find required album in db file
		$found = false;
		$db = $this->getDBFile();
		while (!feof($db)) {
			$line = fgets($db);
			if ($line == "album $albumid\n") {
				$found = true;
				break;
			}
		}
		if (!$found) {
			fwrite(STDERR, "didn't find album $albumid in database file\n");
			return false;
		}
		$found = false;
		while (!feof($db)) {
			$line = fgets($db);
			if (strpos($line, "album ") === 0)
				break;
			$bits = explode(" ", trim($line));
			if ($bits[0] == "$tracknum") {
				$trackid = intval($bits[1]);
				$found = true;
			}
		}
		if (!$found) {
			fwrite(STDERR, "didn't find track $tracknum in album $albumid in database file\n");
			return false;
		}

		$md["albumid"] = $albumid;
		$md["tracknum"] = $tracknum;
		$md["trackid"] = $trackid;

		require_once dirname(dirname(__FILE__)) . "/lib/arc2/ARC2.php";
		require_once dirname(dirname(__FILE__)) . "/lib/Graphite/graphite/Graphite.php";

		$graph = new Graphite($GLOBALS["ns"]);
		$triplecount = $graph->loadSPARQL("http://dbtune.org/jamendo/sparql/", "construct { <http://dbtune.org/jamendo/track/$trackid> owl:sameAs ?mbz . } where { <http://dbtune.org/jamendo/track/$trackid> a mo:Track; owl:sameAs ?mbz . }");
		if (!$triplecount)
			return $md;

		$mburi = $graph->resource("http://dbtune.org/jamendo/track/$trackid")->get("owl:sameAs")->uri;
		if (preg_match('%/track/([0-9a-f-]{36})$%', $mburi, $matches)) {
			$md["mbid"] = $matches[1];
			$md["mbid_source"] = "asserted by DBtune about Jamendo track with ID $trackid";
			return $md;
		}
		fwrite(STDERR, "got a triple from DBtune but it didn't match the expected format\n");
		return false;
	}
}
