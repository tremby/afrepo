<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

// call ffmpeg to determine track length, no matter what format it is
// return the length in seconds
function medialength($filepath) {
	if (!file_exists($filepath))
		throw new Exception("tried to get length of media file '$filepath' which doesn't exist");
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
		throw new Exception("ffmpeg exited with code $code (should be 1 since no output file is specified) when trying to determine length of file '$filepath'");

	$matches = null;
	if (!preg_match('%.*Duration: (..):(..):(..)\.(..).*%', $out, $matches))
		throw new Exception("ffmpeg didn't return a duration for file '$filepath'");

	return floatVal($matches[4] / 100) + intVal($matches[3]) + intVal($matches[2]) * 60 + intVal($matches[1]) * 60 * 60;
}

// look up an artist and title to find a Musicbrainz ID
function musicbrainzLookup($artist, $title, $limit = 1, &$fullresponse = null) {
	// not using Work at the moment since they don't seem to exist for many 
	// songs
	//$resource = "work";
	//$querybits = array(
	//	"artist" => $artist, // for work
	//	"work" => $title, // for work
	//);
	$resource = "recording";
	$querybits = array(
		"artistname" => $artist, // for recording
		"recording" => $title, // for recording
	);
	$tmp = array();
	foreach ($querybits as $k => $v)
		$tmp[] = $k . ":\"$v\"";
	$query = urlencode(implode(" ", $tmp));
	$url = "http://musicbrainz.org/ws/2/$resource/?limit=$limit&query=$query";

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($curl);
	if ($response === false) {
		fwrite(STDERR, "curl error: '" . curl_error($curl) . "'");
		return false;
	}

	@$fullresponse = $response;

	$xml = simplexml_load_string($response);
	if (!$xml) {
		trigger_error("couldn't parse XML from Musicbrainz", E_USER_WARNING);
		return false;
	}

	$mbids = array();
	foreach ($xml->{"recording-list"}->recording as $recording)
		$mbids[] = (string) $recording["id"];

	if (count($mbids) == 0)
		return false;
	if ($limit == 1)
		return $mbids[0];
	return $mbids;
}

?>
