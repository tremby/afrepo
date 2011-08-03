<?php

require_once "AFRepo.class.php";
require_once "classifiers/AFClassifierBase.class.php";
require_once "functions.php";

ini_set("memory_limit", "256M");

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

?>
