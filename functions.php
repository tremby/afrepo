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

// return a Musicbrainz mo:Signal URI for a given Musicbrainz "recording" ID
function mbidToSignalURI($mbid) {
	return "mbz:recording/$mbid#_";
}

// exit with various statuses
function notfound($message = "not found\n", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 404);
	echo $message;
	exit;
}
function badrequest($message = "bad request\n", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 400);
	echo $message;
	exit;
}
function notacceptable($message = "not acceptable\n", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 406);
	echo $message;
	exit;
}
function multiplechoices($location = null, $message = "multiple choices -- be specific about what you accept\n", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 300);
	if (!is_null($location))
		header("Location: $location");
	echo $message;
	exit;
}
function notauthorized($realm = null, $message = "unauthorized\n") {
	if (is_null($realm)) {
		header("Content-Type: text/plain", true, 403);
		echo $message;
		exit;
	}
	header("Content-Type: text/plain", true, 401);
	header("WWW-Authenticate: Basic realm=\"$realm\"");
	echo $message;
	exit;
}

// redirect to another URL
function redirect($destination = null, $code = 301) {
	$names = array(
		301 => "Moved Permanently",
		302 => "Found",
		303 => "See Other",
	);

	// redirect to current URI by default
	if (is_null($destination))
		$destination = $_SERVER["REQUEST_URI"];

	// HTTP spec says location has to be absolute
	if ($destination[0] == "/")
		// absolute path on this host
		$destination = ($_SERVER["SERVER_PORT"] == 443 ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $destination;
	else if (parse_url($destination, PHP_URL_SCHEME) === null)
		// relative path on this host
		$destination = ($_SERVER["SERVER_PORT"] = 443 ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["REQUEST_URI"]) . "/" . $destination;

	header("Location: " . $destination, true, $code);

	// give some HTML or plain text advising the user of the new URL should 
	// their user agent not redirect automatically
	switch (preferredtype(array("text/plain", "text/html"))) {
		case "text/html":
			header("Content-Type: text/html");
			?>
			<h1><?php echo htmlspecialchars($names[$code]); ?></h1>
			<a href="<?php echo htmlspecialchars($destination); ?>">Redirect to <?php echo htmlspecialchars($destination); ?></a>
			<?php
			break;
		case "text/plain":
		default:
			header("Content-Type: text/plain");
			echo $names[$code] . "\n";
			echo $destination . "\n";
			break;
	}
	exit;
}

// return the user agent's preferred accepted type, given a list of available 
// types. or return true if there is no preference or false if no available type 
// is acceptable
function preferredtype($types = array("text/html")) {
	$acceptstring = strtolower(@$_SERVER["HTTP_ACCEPT"]);

	// if there's no accept string that's equivalent to */* -- no preference
	if (empty($acceptstring))
		return true;

	// build an array of mimetype to score, sort it descending
	$atscores = array();
	$accept = preg_split("/\s*,\s*/", $acceptstring);
	foreach ($accept as $part) {
		if (strpos($part, ";") !== false) {
			$type = explode(";", $part);
			$score = explode("=", $type[1]);
			$atscores[$type[0]] = $score[1];
		} else
			$atscores[$part] = 1;
	}
	arsort($atscores);

	// return the first match of accepted to offered, if any
	foreach ($atscores as $wantedtype => $score)
		if (in_array($wantedtype, $types))
			return $wantedtype;

	// no specific type accepted is offered -- look for type/*
	$allsubtypesof = array();
	foreach ($atscores as $wantedtype => $score) {
		$typeparts = explode("/", $wantedtype);
		if ($typeparts[1] == "*")
			$allsubtypesof[$typeparts[0]] = $score;
	}
	arsort($allsubtypesof);

	// match against offered types
	foreach ($allsubtypesof as $accepted => $score)
		foreach ($types as $offered)
			if (preg_replace('%(.*)/.*$%', '\1', $offered) == $accepted)
				return $offered;

	// if they accept */*, return true (no preference)
	if (in_array("*/*", array_keys($atscores)))
		return true;

	// return false -- we don't offer any accepted type
	return false;
}

// issue a Last-Modified header in the correct format, given a timestamp
function lastmodified($timestamp) {
	header("Last-Modified: " . gmdate('D, d M Y H:i:s \G\M\T', $timestamp));
}

// return true if a given IP address is in the given range
// accepts
// - plain dotted IP address
// - integer IP address
// - CIDR range (128.222.70.0/16 etc)
// - array of any of the above
function ipInRange($needle, $haystack) {
	// recurse if we have an array
	if (is_array($haystack)) {
		foreach ($haystack as $h)
			if (ipInRange($needle, $h))
				return true;
		return false;
	}

	// turn needle into an integer IP address
	if (is_string($needle))
		$needle = ip2long($needle);

	// integer
	if (is_long($haystack))
		return $needle == $haystack;

	// plain IP address
	if (preg_match('%^[0-9.]+$%', $haystack))
		return $needle == ip2long($haystack);

	// CIDR
	trigger_error("haystack is $haystack", E_USER_NOTICE);
	if (preg_match('%^[0-9.]+/\d+$%', $haystack)) {
		list ($net, $mask) = split("/", $haystack);
		$ip_net = ip2long($net);
		$ip_mask = ~((1 << (32 - $mask)) - 1);

		$ip_ip_net = $needle & $ip_mask;
		return $ip_ip_net == $ip_net;
	}

	throw new Exception("haystack in unexpected format");
}

?>
