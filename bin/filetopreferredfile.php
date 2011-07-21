#!/usr/bin/env php
<?php

if (count($_SERVER["argv"]) != 3) {
	fwrite(STDERR, "expected two arguments, the repository class name and the filename of the audio file\n");
	exit(255);
}

$classname = $_SERVER["argv"][1];
require_once dirname(dirname(__FILE__)) . "/AFRepo.class.php";
$repository = AFRepo::getRepository($classname);

$file = $_SERVER["argv"][2];

$preferred = $repository->getPreferredFile($file);
if ($preferred === false)
	exit(1);
echo $preferred . "\n";
exit;

?>
