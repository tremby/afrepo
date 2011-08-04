<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

class EchonestClassifier extends AFClassifierBase {
	private function getAPIKey() {
		return "1WJE4HXGNDRCZ7MMK";
	}
	private function getFingerprinterPath() {
		return dirname(dirname(__FILE__)) . "/lib/codegen-3.15/codegen.Linux-x86_64";
	}

	public function getName() {
		return "Echonest";
	}
	public function getDescription() {
		return "Fingerprints the track using ENMFP and submits the fingerprint to Echonest for identification. The Echonest ID is then sent back to Echonest to query for a Musicbrainz recording ID. If none is returned, the artist and title according to Echonest are used in a Musicbrainz query to get the Musicbrainz ID. If a Musicbrainz ID is found, artist and title are then taken from Musicbrainz.";
	}

	protected function runClassifier($filepath) {
		if (!is_executable($this->getFingerprinterPath()))
			throw new Exception("fingerprinter (expected at '" . $this->getFingerprinterPath() . "') doesn't exist or is not executable");

		$length = medialength($filepath);

		if ($length <= 90) {
			$query_text = $this->fingerprint($filepath);
			if (!$query_text)
				return false;
			$en_response = $this->queryechonest($query_text);
		} else {
			$query_text = $this->fingerprint($filepath, 30, 60);
			if (!$query_text)
				return false;
			$en_response = $this->queryechonest($query_text);
			if (count($en_response["response"]["songs"]) == 0) {
				$query_text = $this->fingerprint($filepath, 30, 120);
				if (!$query_text)
					return false;
				$en_response = $this->queryechonest($query_text);
				if (count($en_response["response"]["songs"]) == 0) {
					$query_text = $this->fingerprint($filepath, 0, 300);
					if (!$query_text)
						return false;
					$en_response = $this->queryechonest($query_text);
				}
			}
		}

		$md = array(
			"enmfp_output" => $query_text,
			"en_identify_response" => $en_response,
		);

		if ($en_response["response"]["status"]["message"] != "Success") {
			fwrite(STDERR, "got non-success message '" . $en_response["response"]["status"]["message"] . "'\n");
			fwrite(STDERR, print_r($en_response, true));
			return false;
		}

		// if there are no songs, we can't look for a MBID
		if (!empty($en_response["response"]["songs"])) {
			$md["mbid_response"] = $this->queryForMBID($en_response["response"]["songs"][0]["id"]);
			if ($md["mbid_response"]["response"]["status"]["message"] != "Success") {
				fwrite(STDERR, "got non-success message '" . $md["mbid_response"]["response"]["status"]["message"] . "'\n");
				fwrite(STDERR, print_r($md["mbid_response"], true));
				return false;
			}
			if (isset($md["mbid_response"]["response"]["songs"][0]["foreign_ids"]))
				$md["mbid"] = preg_replace('%^musicbrainz:song:%', "", $md["mbid_response"]["response"]["songs"][0]["foreign_ids"][0]["foreign_id"]);
			if (!isset($md["mbid"]) || empty($md["mbid"])) {
				$artist = @$md["mbid_response"]["response"]["songs"][0]["artist_name"];
				if (!$artist)
					$artist = $md["en_identify_response"]["response"]["songs"][0]["artist_name"];
				$title = @$md["mbid_response"]["response"]["songs"][0]["title"];
				if (!$title)
					$title = $md["en_identify_response"]["response"]["songs"][0]["title"];
				$md["mbid"] = musicbrainzLookup($artist, $title);
			}
		}

		return $md;
	}

	protected function fingerprint($path, $start = null, $duration = null) {
		$args = array($this->getFingerprinterPath(), $path);
		if (!is_null($start) || !is_null($duration)) {
			if (!is_null($start))
				$args[] = "0";
			else
				$args[] = (string) $start;
			if (!is_null($duration))
				$args[] = (string) $duration;
		}
		foreach ($args as &$arg)
			$arg = escapeshellarg($arg);

		$fdspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w"),
		);
		$enmfp_proc = proc_open(implode(" ", $args), $fdspec, $pipes);
		fclose($pipes[0]);
		$query_text = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$error_output = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$returncode = proc_close($enmfp_proc);


		/*
		// TODO: detect if it aborted and then try normalizing the track with 
		// sox before trying to fingerprint again
		// Here is the Python to do that
			if enmfp_proc.returncode == -6:
				print >>sys.stderr, "enmfp exited with signal -6 (it aborted). trying to normalize the track and re-fingerprint"

				# make fifo for audio
				tmpdir = tempfile.mkdtemp()
				fifopath = os.path.join(tmpdir, "fifo")
				try:
					os.mkfifo(fifopath)
				except OSError, e:
					print >>sys.stderr, "failed to create fifo: %s" % e
					sys.exit(14)

				# get boost amount
				sox_scan = subprocess.Popen(["sox", path, "-t", "wav", "/dev/null", "stat", "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
				sox_scan.wait()
				sox_scan_out = sox_scan.communicate()
				sox_scan.stdout.close()
				sox_scan.stderr.close()
				if sox_scan.returncode != 0:
					print >>sys.stderr, "sox (stat) exited with non-zero code %d" % sox_scan.returncode
					sys.exit(14)
				for line in string.split(sox_scan_out[1], "\n"):
					if line[0:4] == "sox:" or len(line) == 0:
						continue
					boost = line

				# boost and put through fifo to enmfp, wait for enmfp to exit
				args[1] = fifopath
				enmfp_proc = subprocess.Popen(args, stdout=subprocess.PIPE)
				sox_boost = subprocess.Popen(["sox", path, "-v", boost, "-t", "wav", fifopath])
				enmfp_proc.wait()

				# sox should have exited by now. if not something may be wrong
				if sox_boost.returncode is None:
					print >>sys.stderr, "sox (boost) is still running even though enmfp has exited. something may be wrong. killing it."
					sox_boost.kill()
				elif sox_boost.returncode != 0:
					print >>sys.stderr, "sox (boost) exited with non-zero code %d" % sox_boost.returncode
					sys.exit(15)

				# get enmfp's stdout
				query_text = enmfp_proc.communicate()[0]
				enmfp_proc.stdout.close()
				print >>sys.stderr, "enmfp's new response:"
				print >>sys.stderr, query_text

				# remove fifo and its dir
				os.remove(fifopath)
				os.rmdir(tmpdir)

				if enmfp_proc.returncode < 0:
					print >>sys.stderr, "enmfp was terminated by signal %d" % -enmfp_proc.returncode
					sys.exit(16)
				elif enmfp_proc.returncode != 1:
					print >>sys.stderr, "enmfp exited with code %d" % enmfp_proc.returncode
					sys.exit(17)
				if ($returncode != 1) {
					fwrite(STDERR, "enmfp exited with code $returncode\n");
					return false;
				}
		 */

		$formed_out = json_decode($query_text, true);
		if (!empty($error_output) || is_null($formed_out) || !isset($formed_out[0])) {
			fwrite(STDERR, "unexpected output from enmfp: '$query_text'\nerror output: '$error_output'\n");
			return false;
		}
		if (isset($formed_out[0]["error"])) {
			fwrite(STDERR, "got error message from enmfp: '" . $formed_out[0]["error"] . "'\n");
			return false;
		}
		if (empty($formed_out[0]["code"])) {
			fwrite(STDERR, "got an empty code from fingerprinter\n");
			return false;
		}

		return $formed_out;
	}

	private function queryechonest($query_json) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "http://developer.echonest.com/api/v4/song/identify?api_key=" . $this->getAPIKey());
		curl_setopt($curl, CURLOPT_POSTFIELDS, "query=" . json_encode($query_json));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		if ($response === false) {
			fwrite(STDERR, "curl error: '" . curl_error($curl) . "'");
			return false;
		}

		return json_decode($response, true);
	}

	private function queryForMBID($enid) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "http://developer.echonest.com/api/v4/song/profile?api_key=" . $this->getAPIKey() . "&format=json&bucket=id:musicbrainz&id=" . $enid);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		if ($response === false) {
			fwrite(STDERR, "curl error: '" . curl_error($curl) . "'");
			return false;
		}

		return json_decode($response, true);
	}
}
