<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 *
 * AFClassifierBase
 *
 * This is the base Classifier class. It is extended for the various available 
 * classifiers, more of which can (and probably should) be made for each 
 * repository.
 */

abstract class AFClassifierBase {
	/**
	 * getName
	 * Return the name of this classifier (a short string)
	 */
	abstract public function getName();

	/**
	 * getDescription
	 * Return the description of this classifier (a sentence or two)
	 */
	abstract public function getDescription();

	/**
	 * available
	 * Return true if the classifier is available for use
	 */
	public function available() {
		return true;
	}

	/**
	 * getDataPath
	 * Return the path of the file where this classifier saves its data for the 
	 * audiofile with the given ID
	 */
	public function getDataPath($id) {
		$repo = new AFRepo();
		$filepath = $repo->idToLinkPath($id);
		return dirname($filepath) . "/." . basename($filepath) . "." . get_class($this) . ".data";
	}

	/**
	 * hasMetadata
	 * Return true if there is saved metadata for this classifier for the 
	 * audiofile with the given ID
	 */
	public function hasMetadata($id) {
		return file_exists($this->getDataPath($id));
	}

	/**
	 * loadMetadata
	 * Return the saved metadata for this classifier for the audiofile with the 
	 * given ID or false
	 */
	public function loadMetadata($id) {
		if (!$this->hasMetadata($id))
			return false;
		return unserialize(file_get_contents($this->getDataPath($id)));
	}

	/**
	 * classify
	 * Invoke the classifier on the audiofile with the given ID
	 *
	 * If the force argument is false existing data will be loaded. If there is 
	 * no existing data or if the force argument is true the classifier will be 
	 * run (via the runClassifier method) and the resulting metadata saved and 
	 * returned.
	 */
	public function classify($id, $force = false) {
		$repo = new AFRepo();
		$filepath = $repo->idToLinkPath($id);
		if (!$force && $this->hasMetadata($id))
			return $this->loadMetadata($id);

		if (realpath($filepath) === false)
			throw new Exception("can't get real path of file '$filepath', which is file ID '$id'. broken symlink?");

		$metadata = $this->runClassifier($filepath);
		if ($metadata !== false)
			file_put_contents($this->getDataPath($id), serialize($metadata));
		return $metadata;
	}

	/**
	 * runClassifier
	 * Classify the audio file with the given path and return metadata about it
	 *
	 * The metadata should be an array. It will hopefully have the key "mbid". 
	 * The Musicbrainz ID should be looked up with artist and title information 
	 * if necessary (possibly using the musicbrainzLookup() function).
	 *
	 * It is recommended to also save a string corresponding to the key 
	 * "mbid_source", providing provenance information for the Musicbrainz ID.
	 *
	 * If the Musicbrainz ID can't be found, the value can be null or the key 
	 * can be missing altogether.
	 *
	 * Return false on an error.
	 */
	abstract protected function runClassifier($filepath);

	/**
	 * hasMBID
	 * Return true if this classifier has found a MBID for the audio file with 
	 * the given ID
	 */
	public function hasMBID($id) {
		if (!$this->hasMetadata($id))
			return false;
		$mbid = $this->getMBID($id);
		return !is_null($mbid);
	}

	/**
	 * getMBID
	 * Return the Musicbrainz ID of the audio file with the given ID according 
	 * to this classifier
	 */
	public function getMBID($id) {
		$metadata = $this->classify($id);
		if (isset($metadata["mbid"]) && !empty($metadata["mbid"]))
			return $metadata["mbid"];
		return null;
	}

	/**
	 * hasProvenance
	 * Return true if this classifier has stored provenance information 
	 * regarding the MBID found for the audiofile with the given ID
	 */
	public function hasProvenance($id) {
		if (!$this->hasMBID($id))
			return false;
		$provenance = $this->getProvenance($id);
		return !is_null($provenance);
	}

	/**
	 * getProvenance
	 * Return the provenance information regarding the MBID found for the 
	 * audiofile with the given ID
	 */
	public function getProvenance($id) {
		$metadata = $this->classify($id);
		if (isset($metadata["mbid_source"]) && !empty($metadata["mbid_source"]))
			return $metadata["mbid_source"];
		return null;
	}

	/**
	 * getLastMetadataChange
	 * Return the timestamp of the last time the metadata changed for the 
	 * audiofile with the given ID
	 */
	public function getLastMetadataChange($id) {
		if (!$this->hasMetadata($id))
			return false;
		return filemtime($this->getDataPath($id));
	}
}

?>
