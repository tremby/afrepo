<?php

require_once "functions.php";

ini_set("memory_limit", "256M");

$ns = array(
	"mo" => "http://purl.org/ontology/mo/",
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
