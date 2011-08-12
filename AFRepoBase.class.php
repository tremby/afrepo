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
	const LAST_RDF_STRUCTURE_CHANGE = "2011-08-12 21:00 BST";

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
	 * getAllPreferredFiles
	 * Get a backwards array containing canonical pathnames for every preferred 
	 * file in the repository (that is, the best version of each song)
	 */
	public function getAllPreferredFiles() {
		$preferred = array();
		foreach (array_keys($this->getAllFiles()) as $file)
			$preferred[$this->getPreferredFile($this->filePathToId($file))] = true;
		return $preferred;
	}

	/**
	 * fileInRepo
	 * Return true if the audiofile with the given path (canonical or symlink) 
	 * is in the repository or false if not
	 */
	public function fileInRepo($filepath) {
		$realpath = realpath($filepath);
		if ($realpath === false) {
			trigger_error("file '$filepath' does not exist on disk or is a broken symlink", E_USER_WARNING);
			return false;
		}
		return array_key_exists($realpath, $this->getAllFiles());
	}

	/**
	 * inRepo
	 * Return true if the audiofile with the given ID is in the repository or 
	 * false if not
	 *
	 * Note that this assumes all symlinks have been made.
	 */
	public function inRepo($id) {
		return file_exists($this->idToLinkPath($id));
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
		if (!$this->inRepo($id))
			return array();
		return array($this->idToCanonicalPath($id));
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
	 * Return the path to the symlink of the audiofile with the given ID
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
		if (!$this->fileInRepo($filepath))
			throw new Exception("file with path '$filepath' is not in the repository");
		$id = md5(realpath($filepath));
		return $id;
	}

	/**
	 * filePathToLinkPath
	 * Return the path to the symlink of the given audiofile (which could be a 
	 * symlink or canonical)
	 */
	public function filePathToLinkPath($filepath) {
		return $this->idToLinkPath($this->filePathToId($filepath));
	}

	/**
	 * getRDFPath
	 * Return the path on disk of the RDF representation of the audiofile with 
	 * the given ID
	 *
	 * Note that there is only one RDF representation per song -- that is, many 
	 * audiofiles may share an RDF representation.
	 */
	public function getRDFPath($id) {
		return dirname(__FILE__) . "/rdf/" . self::splitId($this->getPreferredId($id)) . ".xml";
	}

	/**
	 * getRDF
	 * Return the RDF for the audiofile with the given ID in the given format
	 *
	 * The RDF is generated first if necessary.
	 */
	public function getRDF($id, $format = "RDFXML", $force = false) {
		if ($force
			|| !file_exists($this->getRDFPath($id))
			|| filemtime($this->getRDFPath($id)) < strtotime(self::LAST_RDF_STRUCTURE_CHANGE)
			|| filemtime($this->getRDFPath($id)) < $this->getLastMetadataChange($id)
		)
			$this->generateRDF($id);

		if ($format == "RDFXML")
			// return existing RDF
			return file_get_contents($this->getRDFPath($id));

		// convert saved RDF to the required format
		require_once dirname(__FILE__) . "/lib/arc2/ARC2.php";

		$parser = ARC2::getRDFParser();
		$parser->parse($this->getURIPrefix(), file_get_contents($this->getRDFPath($id)));
		$serializer = ARC2::getSer($format, array("ns" => $GLOBALS["ns"]));
		$rdf = $serializer->getSerializedTriples($parser->getTriples());
		if (substr($rdf, -1) != "\n")
			$rdf .= "\n";
		return $rdf;
	}

	/**
	 * generateRDF
	 * Generate and save RDF for the audiofile with the given ID
	 */
	protected function generateRDF($id) {
		$files = $this->getSongFiles($id);
		$ids = array_map(array($this, "filePathToId"), $files);

		$mbid = $this->getMBID($id);

		$triples = array();

		// loop through the audiofiles for this tune
		foreach ($ids as $key => $fileid) {
			// some identifiers
			$audiofile = "repo:$fileid";
			$digitalsignal = "repo:$fileid#DigitalSignal";

			// triples about the document
			$triples[] = "{$audiofile}_ a foaf:Document; foaf:primaryTopic $audiofile";

			// this is a mo:AudioFile, which is a mo:MusicalItem
			$triples[] = "$audiofile a mo:AudioFile";

			// this encodes a corresponding digital signal
			$triples[] = "$audiofile mo:encodes $digitalsignal";

			// our digital signal is a mo:DigitalSignal (which is a subclass of 
			// mo:Signal, which is a subclass of mo:MusicalExpression)
			$triples[] = "$digitalsignal a mo:DigitalSignal";

			// different logic depending whether this audiofile is the preferred 
			// one or not
			if ($key == 0) {
				// preferred -- if we have an MBID this Signal is derived from 
				// the original (that at Musicbrainz)
				if (!is_null($mbid))
					$triples[] = "$digitalsignal mo:derived_from " . mbidToSignalURI($mbid);
				// otherwise we don't assert that it derives from anything
			} else {
				// non-preferred -- we assert that it is derived from our 
				// preferred audiofile's Signal
				$triples[] = "$digitalsignal mo:derived_from repo:" . $ids[0] . "#DigitalSignal";
			}

			// analyze the file, get some metadata
			$filemetadata = $this->getFileMetadata($fileid);

			// mo:AudioFile metadata
			if (isset($filemetadata["dataformat"]))
				$triples[] = "$audiofile mo:encoding \"" . $filemetadata["dataformat"]
						. (isset($filemetadata["bitrate"]) ? " @ " . $filemetadata["bitrate"] . "bps" : "")
						. (isset($filemetadata["bitrate_mode"]) ? " " . $filemetadata["bitrate_mode"] : "")
						. "\"";

			// mo:DigitalSignal metadata
			if (isset($filemetadata["playtime_seconds"]))
				$triples[] = "$digitalsignal mo:time [ "
						. "a time:Interval; "
						. "time:seconds \"" . $filemetadata["playtime_seconds"] . "\"^^xsd:float "
						. "]";
			if (isset($filemetadata["channels"]))
				$triples[] = "$digitalsignal mo:channels \"" . $filemetadata["channels"] . "\"^^xsd:int";
			if (isset($filemetadata["sample_rate"]))
				$triples[] = "$digitalsignal mo:sample_rate \"" . $filemetadata["sample_rate"] . "\"^^xsd:float";

		}

		// load Arc and Graphite
		require_once dirname(__FILE__) . "/lib/arc2/ARC2.php";
		require_once dirname(__FILE__) . "/lib/Graphite/graphite/Graphite.php";
		$graph = new Graphite($GLOBALS["ns"]);

		// turn those triples into Turtle
		$ttl = "";
		foreach ($GLOBALS["ns"] as $short => $long)
			$ttl .= "@prefix $short: <$long> .\n";
		foreach ($triples as $triple)
			$ttl .= $triple . " .\n";

		// have Arc parse them and give any errors
		$parser = ARC2::getTurtleParser();
		$parser->parse($this->getURIPrefix(), $ttl);
		$errors = $parser->getErrors();
		if (!empty($errors))
			throw new Exception("arc couldn't parse the generated Turtle. errors:\n"
					. "\t- " . implode("\n\t- ", $errors) . "\n"
					. "turtle:\n" . $turtle);

		$serializer = ARC2::getRDFXMLSerializer(array("ns" => $GLOBALS["ns"]));
		$rdfxml = $serializer->getSerializedTriples($parser->getTriples());
		if (substr($rdfxml, -1) != "\n")
			$rdfxml .= "\n";

		$rdfpath = $this->getRDFPath($ids[0]);
		if (!is_dir(dirname($rdfpath)))
			mkdir(dirname($rdfpath));
		if (!file_put_contents($rdfpath, $rdfxml))
			throw new Exception("couldn't save RDFXML to '$rdfpath'");

		return $rdfxml;
	}

	/**
	 * getMBID
	 * Get the preferred MBID for the audiofile with the given ID according to 
	 * the classifiers which have been run
	 *
	 * Return null if no MBID is available.
	 *
	 * By default this method has a built-in order of preference of classifiers 
	 * -- this should almost certainly be overridden in implementations.
	 */
	public function getMBID($id) {
		$classifiers = array(
			new TagClassifier(),
			new EchonestClassifier(),
			new ImirselDbClassifier(),
			new SalamiClassifier(),
		);
		foreach ($classifiers as $classifier)
			if ($classifier->available() && $classifier->hasMBID($id))
				return $classifier->getMBID($id);
		return null;
	}

	/**
	 * getFileMetadata
	 * Analyze the audiofile with the given ID to return an array with some 
	 * information about its encoding
	 */
	public function getFileMetadata($id) {
		require_once dirname(__FILE__) . "/lib/getid3-1.9.0-20110620/getid3/getid3.php";
		$getID3 = new getID3();
		$fileinfo = $getID3->analyze($this->idToCanonicalPath($id));
		$md = array();
		if (isset($fileinfo["audio"]["dataformat"]))
			$md["dataformat"] = $fileinfo["audio"]["dataformat"];
		if (isset($fileinfo["audio"]["channels"]))
			$md["channels"] = $fileinfo["audio"]["channels"];
		if (isset($fileinfo["audio"]["sample_rate"]))
			$md["sample_rate"] = $fileinfo["audio"]["sample_rate"];
		if (isset($fileinfo["audio"]["bitrate"]))
			$md["bitrate"] = $fileinfo["audio"]["bitrate"];
		if (isset($fileinfo["audio"]["bitrate_mode"]))
			$md["bitrate_mode"] = $fileinfo["audio"]["bitrate_mode"];
		if (isset($fileinfo["audio"]["channelmode"]))
			$md["channelmode"] = $fileinfo["audio"]["channelmode"];
		if (isset($fileinfo["playtime_seconds"]))
			$md["playtime_seconds"] = $fileinfo["playtime_seconds"];
		if (isset($fileinfo["mime_type"])) {
			// replace application/ogg with audio/ogg -- application/ogg is the 
			// official one but audio/ogg is also common and in my opinion is 
			// better suited. on top of that it allows people to ask for audio/* 
			// and get any audio format we provide.
			if ($fileinfo["mime_type"] == "application/ogg")
				$fileinfo["mime_type"] = "audio/ogg";
			$md["mime_type"] = $fileinfo["mime_type"];
		}
		return $md;
	}

	/**
	 * getLastMetadataChange
	 * Return the timestamp of the last time the metadata changed for any 
	 * classifier for the audiofile with the given ID
	 */
	public function getLastMetadataChange($id) {
		$lastchange = -1;
		foreach (allclassifiers() as $classifier)
			if ($classifier->hasMetadata($id))
				$lastchange = max($lastchange, $classifier->getLastMetadataChange($id));
		if ($lastchange == -1)
			return false;
		return $lastchange;
	}
}

?>
