<?php

require_once "setup.php";

// require TLS?
define("REQUIRE_TLS", false);
define("REQUIRE_CLIENT_CERTIFICATE", false);

// check scheme
if ($_SERVER["SERVER_PORT"] == 443)
	define("SCHEME", "https");
else if (REQUIRE_TLS) {
	define("SCHEME", "https");
	redirect("https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
} else
	define("SCHEME", "http");

// fail if client certificate is required but one was not given
if (REQUIRE_CLIENT_CERTIFICATE && (!isset($_SERVER["SSL_CLIENT_VERIFY"]) || $_SERVER["SSL_CLIENT_VERIFY"] != "SUCCESS"))
	notauthorized(null, "client certificate required but a valid one was not given\n");

// get base path
$base = parse_url($repo->getURIPrefix());
$basepath = $base["path"];

// check the request URI is the expected form
if (!preg_match('%^' . preg_quote($basepath, "%") . '[0-9a-f]{32}_?$%', $_SERVER["REQUEST_URI"]))
	notfound("Not found. Expected a request URI ending in the audiofile ID and optionally an underscore (to denote the information resource).");

$id = preg_replace('%.*/([0-9a-f]{32})_?$%', '\1', $_SERVER["REQUEST_URI"]);
$ir = substr($_SERVER["REQUEST_URI"], -1) == "_";

if (!$repo->inRepo($id))
	notfound("Not found. Given ID '$id' does not exist in the repository");

// accepted extensions to their mimetypes
$rdftypes = array(
	"application/rdf+xml" => "RDFXML",
	"application/rdf+json" => "RDFJSON",
	"text/turtle" => "Turtle",
	"text/plain" => "NTriples",
);
$types = array_merge(array_keys($rdftypes), array(
	// HTML
	"text/html", //HTML
));

// get audio mimetype
// TODO: access control on the audio data
$md = $repo->getFileMetadata($id);
if (isset($md["mime_type"]))
	$types[] = $md["mime_type"];
else
	trigger_error("getID3 didn't give a mimetype for audiofile with ID $id", E_USER_WARNING);

// determine the preferred mimetype
$preferredtype = preferredtype($types);

// choose what to do based on that preferred type
header("Vary: Accept");

// no preference
if ($preferredtype === true)
	multiplechoices(null, "Multiple choices -- be specific about what you accept. Available types:\n- " . implode("\n- ", $types) . "\n");

// non-offered type
if ($preferredtype === false)
	notacceptable("Not acceptable -- none of the types you accept are available. Available types:\n- " . implode("\n- ", $types) . "\n");

// an RDF type
if (in_array($preferredtype, array_keys($rdftypes))) {
	// if we're at the NIR URI, redirect to IR URI
	if (!$ir)
		redirect($repo->getURIPrefix() . $id . "_", 303);

	$output = $repo->getRDF($id, $rdftypes[$preferredtype]);
	header("Content-Type: $preferredtype; charset=utf-8");
	header("Content-Length: " . strlen($output));
	lastmodified(filemtime($repo->getRDFPath($id)));
	echo $output;
	exit;
}

// HTML
if ($preferredtype == "text/html") {
	// if we're at the NIR URI, redirect to IR URI
	if (!$ir)
		redirect($repo->getURIPrefix() . $id . "_", 303);
	// load Graphite
	require_once "lib/arc2/ARC2.php";
	require_once "lib/Graphite/graphite/Graphite.php";

	// load graph
	$graph = new Graphite($ns);
	$graph->load($repo->getURIPrefix() . $id);

	ob_start();
	?>
<!DOCTYPE HTML>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title><?php echo htmlspecialchars($repo->getName()); ?>: audiofile <?php echo htmlspecialchars($id); ?></title>
	</head>
	<body>
		<h1><?php echo htmlspecialchars($repo->getName()); ?>: audiofile <code><?php echo htmlspecialchars($id); ?></h1>
		<p>You have this HTML representation because according to your 
		<code>Accept</code> header it is your preferred format of those offered. 
		The available formats are</p>
		<ul>
			<?php foreach ($types as $type) { ?>
				<li><code><?php echo htmlspecialchars($type); ?></code></li>
			<?php } ?>
		</ul>
		<?php echo $graph->dump(array("label" => true, "labeluris" => true, "internallinks" => true)); ?>
	</body>
</html>
<?php
	$output = ob_get_clean();
	header("Content-Type: $preferredtype; charset=utf-8");
	header("Content-Length: " . strlen($output));
	lastmodified(filemtime($repo->getRDFPath($id)));
	echo $output;
	exit;
}

// audio
if (preg_match('%^audio/%', $preferredtype)) {
	// if we're at the IR URI, redirect to NIR URI
	if ($ir)
		redirect($repo->getURIPrefix() . $id, 303);

	header("Content-Type: $preferredtype");
	header("Content-Length: " . filesize($repo->idToCanonicalPath($id)));
	lastmodified(filemtime($repo->idToCanonicalPath($id)));
	header("Content-Transfer-Encoding: binary");
	readfile($repo->idToCanonicalPath($id));
	exit;
}

// something has gone wrong
trigger_error("got to the end of the webrequest script -- shouldn't be there", E_USER_ERROR);
servererror();

?>
