<?php

// FIXME: this is in a broken state

require_once "lib/arc2/ARC2.php";
require_once "lib/Graphite/graphite/Graphite.php";

define("RDF_DIR", dirname(__FILE__) . "/rdf");

if (!isset($_SERVER["argv"][2])) //TODO: change to get var
	trigger_error("expected a file ID", E_USER_ERROR);

$repo = new AFRepo();

$fileid = $_SERVER["argv"][2]; //TODO: change to get var
$filepath = $repo->idToLinkPath($fileid);
$rdfpath = RDF_DIR . "/" . AFRepo::splitId($fileid);

// check file exists
if (!file_exists($filepath))
	throw new Exception("file with id '$fileid' doesn't exist");

// if RDF already exists and is newer than this script, use that
// delete it if it's out of date
if (file_exists($rdfpath)) {
	if (filemtime($rdfpath) > filemtime(__FILE__))
		outputrdf($rdfpath);
	else
		unlink($rdfpath);
}

// get the preferred file of this track
$preferredfilepath = $repo->getPreferredFile($filepath);
$preferredfileid = md5($preferredfilepath);
$ispreferred = $fileid == $preferredfileid;

// gather information about the audiofile and its signal
$metadata = $repo->getMetadata($filepath);
if (!$ispreferred)
	$preferredmetadata = $repo->getMetadata($preferredfilepath);

// get Musicbrainz ID
$mburi = $repo->getMusicbrainzID($preferredfilepath);

// RDF
// mo:DigitalSignal (is a mo:Signal which is a mo:MusicalExpression)
//   - of the full track:
//     - is mo:published_as a mo:Track
//   - of a clip:
//     - is mo:derived_from the full mo:DigitalSignal, which in turn is 
//       mo:published_as a mo:Track as above
// mo:Track (is a mo:MusicalManifestation)
//   - we use existing Musicbrainz URIs here (and DBTune/Musicbrainz URIs too) 
//     if we have them, otherwise self-minted ones
//   - is mo:publication_of the mo:DigitalSignal (reverse of the published_as 
//     predicate)
//   - is mo:available_as the mo:AudioFile
// mo:AudioFile (is a mo:MusicalItem)
//   - holds a bit more metadata about the file (MP3 vs wave)
//   - mo:encodes the mo:DigitalSignal

// decide which mo:Tracks to talk about -- if we don't have a MB ID we have to 
// use a self-minted one
$tracks = array();
if ($mburi) {
	$tracks[] = "<$mburi>";
	$tracks[] = "<http://dbtune.org/musicbrainz/resource/track/" . preg_replace('%.*/%', "", $mburi) . ">";
} else
	$tracks[] = "repository:$preferredfileid#track";

// useful namespaces
$ns = array(
	"rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
	"rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
	"owl" => "http://www.w3.org/2002/07/owl#",
	"event" => "http://purl.org/NET/c4dm/event.owl#",
	"tl" => "http://purl.org/NET/c4dm/timeline.owl#",
	"mo" => "http://purl.org/ontology/mo/",
	"time" => "http://www.w3.org/2006/time#",
	"repository" => $repo->getURIPrefix(),
);

// triple endings for our mo:AudioFile, which is a mo:MusicalItem
$audiofiletriples = array(
	"a mo:AudioFile",
	"mo:encodes repository:$fileid#signal",
);
if (isset($metadata["audiofile"]["encoding"])) {
	switch ($metadata["audiofile"]["encoding"]) {
		case "mp3": $audiofiletriples[] = "mo:media_type \"audio/mpeg\""; break;
		case "wav": $audiofiletriples[] = "mo:media_type \"audio/wave\""; break;
	}
}

// triple endings for our mo:DigitalSignal, which is a mo:MusicalExpression
$signaltriples = array(
	"a mo:DigitalSignal",
	"mo:time [
		a time:Interval ;
		time:seconds " . medialength($filepath) . " ;
	]",
);
if ($ispreferred)
	foreach ($tracks as $track)
		$signaltriples[] = "mo:published_as $track";
if (!$ispreferred)
	$signaltriples[] = "mo:derived_from repository:$preferredfileid#signal";
if (isset($metadata["audiofile"]["sample-rate"]))
	$signaltriples[] = "mo:sample_rate " . floatVal($metadata["audiofile"]["sample-rate"]);
if (isset($metadata["audiofile"]["channels"]))
	$signaltriples[] = "mo:channels " . intVal($metadata["audiofile"]["channels"]);

// triple endings for our preferred mo:DigitalSignal (if this isn't it)
$preferredsignaltriples = array();
if (!$ispreferred) {
	$preferredsignaltriples[] = "a mo:DigitalSignal";
	foreach ($tracks as $track)
		$preferredsignaltriples[] = "mo:published_as $track";
}

// triple endings for the mo:Track objects, which are descendents of 
// mo:MusicalManifestation
$tracktriples = array(
	"a mo:Track",
	"mo:publication_of repository:$fileid#signal",
);
foreach ($repo->getAllFiles($filepath) as $file)
	$tracktriples[] = "mo:available_as repository:" . md5($file);

// turn those triple endings into turtle triples
$turtle = prefix();
$turtle .= "repository:$fileid " . implode(" ;\n\t", $audiofiletriples) . " .\n";
$turtle .= "repository:$fileid#signal " . implode(" ;\n\t", $signaltriples) . " .\n";
foreach ($tracks as $track)
	$turtle .= $track . " " . implode(" ;\n\t", $tracktriples) . " .\n";
if (!empty($preferredsignaltriples))
	$turtle .= "repository:$preferredfileid#signal " . implode(" ;\n\t", $preferredsignaltriples) . " .\n";

// convert that to RDF/XML (leaving it as Turtle at the moment)
$parser = ARC2::getTurtleParser();
$parser->parse($repo->getURIPrefix(), $turtle);
$serializer = ARC2::getRDFXMLSerializer(array("ns" => $ns));
$serializer = ARC2::getTurtleSerializer(array("ns" => $ns)); //TODO: get rid of this, use the above
$output = $serializer->getSerializedTriples($parser->getTriples());

// i like a newline to be at the end
if (substr($output, -1) != "\n")
	$output .= "\n";

// ensure parent directories exist
if (!is_dir(dirname($rdfpath)))
	if (!mkdir(dirname($rdfpath), 0777, true))
		trigger_error("couldn't make dir '" . dirname($rdfpath) . "'", E_USER_ERROR);

// write the RDF to disk
if (file_put_contents($rdfpath, $output) === false)
	trigger_error("failed to write RDF to '$rdfpath'", E_USER_ERROR);

// output the RDF to the browser
outputrdf($rdfpath);

// helper functions
// ----------------

function outputrdf($path) {
	header("Content-Type: text/turtle");
	header("Content-Length: " . filesize($path));
	header("Last-Modified: " . gmdate("D, d M Y H:i:s ", filemtime($path)) . " GMT");
	readfile($path);
	exit;
}

// return a prefix line for a particular prefix (from the global $ns array), or 
// of an array of such prefixes, or, given no argument, of all prefixes
function prefix($n = null) {
	global $ns;
	if (is_null($n))
		$n = array_keys($ns);
	else if (!is_array($n))
		$n = array($n);
	$ret = "";
	foreach ($n as $s)
		$ret .= "prefix $s: <" . $ns[$s] . ">\n";
	return $ret;
}

// return the id with a slash after the first two characters
function splitid($id) {
	return substr($id, 0, 2) . "/" . substr($id, 2);
}

?>
