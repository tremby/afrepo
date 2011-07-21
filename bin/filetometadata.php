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

$meta = $repository->getMetadata($file);

if ($meta === false) {
	fwrite(STDERR, "couldn't get metadata for '$file'\n");
	exit(1);
}

if (isset($meta["signal"]["Artist"]) && isset($meta["signal"]["Title"])) {
	echo $meta["signal"]["Artist"] . "\t" . $meta["signal"]["Title"] . "\n";
	exit;
}
fwrite(STDERR, "file '$file' exists as far as the repository is concerned but artist and/or title for it aren't present\n");
exit(1);


?>
