<?php

/**
 * Bart Nagel <bjn@ecs.soton.ac.uk>
 */

// use getid3 to get length of audio, regardless of format
function medialength($filepath) {
	if (!file_exists($filepath))
		throw new Exception("tried to get length of media file '$filepath' which doesn't exist");

	require_once dirname(__FILE__) . "/lib/getid3-1.9.0-20110620/getid3/getid3.php";

	$getID3 = new getID3();
	$fileinfo = $getID3->analyze($filepath);
	getid3_lib::CopyTagsToComments($fileinfo);

	return $fileinfo["playtime_seconds"];
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

// simulate json_decode and json_encode with an additional library if necessary
if (!function_exists("json_decode")) {
	function json_decode($content, $assoc = false) {
		require_once "lib/json/JSON.php";
		if ($assoc)
			$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		else
			$json = new Services_JSON();
		return $json->decode($content);
	}
}
if (!function_exists("json_encode")) {
	function json_encode($content) {
		require_once "lib/json/JSON.php";
		$json = new Services_JSON();
		return $json->encode($content);
	}
}

if (!function_exists("sys_get_temp_dir")) {
	function sys_get_temp_dir() {
		if ($temp = getenv("TMP"))
			return $temp;
		if ($temp = getenv("TEMP"))
			return $temp;
		if ($temp = getenv("TMPDIR"))
			return $temp;
		$temp = tempnam(__FILE__, "");
		if (file_exists($temp)) {
			unlink($temp);
			return dirname($temp);
		}
		return null;
	}
}

// return an array of all classifiers, indexed by class name
function allclassifiers() {
	$classifiers = array();
	foreach (glob(dirname(__FILE__) . "/classifiers/*Classifier.class.php") as $file) {
		$classname = basename($file, ".class.php");
		$classifier = new $classname;
		if ($classifier->available())
			$classifiers[$classname] = $classifier;
	}
	return $classifiers;
}

// return a particular classifier or false
function getclassifier($classname) {
	if (!preg_match('%Classifier$%', $classname))
		trigger_error("trying to load a classifier which is not named according to convention", E_USER_WARNING);
	if (!class_exists($classname))
		return false;
	$classifier = new $classname;
	if ($classifier->available())
		return $classifier;
	trigger_error("trying to load a classifier which exists but currently declares itself as unavailable", E_USER_WARNING);
	return false;
}

?>
