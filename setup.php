<?php

require_once "functions.php";

ini_set("memory_limit", "256M");
ini_set("log_errors", true);
ini_set("error_log", "error_log");

$repo = new AFRepo();
$ns = array(
	"xsd" => "http://www.w3.org/2001/XMLSchema#",
	"rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
	"rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
	"owl" => "http://www.w3.org/2002/07/owl#",
	"event" => "http://purl.org/NET/c4dm/event.owl#",
	"tl" => "http://purl.org/NET/c4dm/timeline.owl#",
	"mo" => "http://purl.org/ontology/mo/",
	"time" => "http://www.w3.org/2006/time#",
	"repo" => $repo->getURIPrefix(),
	"mbz" => "http://musicbrainz.org/",
	"foaf" => "http://xmlns.com/foaf/0.1/",
);

function __autoload($classname) {
	$base = dirname(__FILE__);
	$file = $classname . ".class.php";
	if (file_exists("$base/$file"))
		require_once "$base/$file";
	else if (file_exists("$base/classifiers/$file"))
		require_once "$base/classifiers/$file";
}

?>
