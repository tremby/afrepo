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

// fail if client doesn't have any permissions
if (!$repo->haveMetadataPermission() && !$repo->haveAudioPermission())
	notauthorized();

// get base path
$base = parse_url($repo->getURIPrefix());
$basepath = $base["path"];

// check the request URI is the expected form
if (!preg_match('%^' . preg_quote($basepath, "%") . '[0-9a-f]{32}_?$%', $_SERVER["REQUEST_URI"]))
	notfound("Not found. Expected a request URI ending in the audiofile ID and optionally an underscore (to denote the infodoc resource).");

$id = preg_replace('%.*/([0-9a-f]{32})_?$%', '\1', $_SERVER["REQUEST_URI"]);
$infodoc = substr($_SERVER["REQUEST_URI"], -1) == "_";

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

// get audiofile's mimetype
$audio_mimetype = null;
$md = $repo->getFileMetadata($id);
if (isset($md["mime_type"]))
	$audio_mimetype = $md["mime_type"];

// if they've asked for the Audiofile resource, the audio is available, 
// otherwise it isn't (we don't have a recording of the RDF document!)
if (!$infodoc) {
	if (!is_null($audio_mimetype))
		$types[] = $audio_mimetype;
	else
		trigger_error("getID3 didn't give a mimetype for audiofile with ID $id", E_USER_WARNING);
}

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

// audio
if (preg_match('%^audio/%', $preferredtype)) {
	// if we're at the infodoc URI, something has gone wrong -- audio shouldn't be 
	// available there
	if ($infodoc)
		servererror("infodoc URI requested but with audio as preferred mime type");

	// check client has audio permission
	if (!$repo->haveAudioPermission())
		notauthorized();

	// get file size
	$size = filesize($repo->idToCanonicalPath($id));

	// allow parts of the file to be requested
	header("Accept-Ranges: bytes");

	// did they request a range?
	if (isset($_SERVER["HTTP_RANGE"]) && ($pos = strpos($_SERVER["HTTP_RANGE"], "bytes=")) !== false) {
		$ranges = explode(",", substr($_SERVER["HTTP_RANGE"], $pos + 6));

		//only look at the first one for simplicity
		$range = $ranges[0];
		list($from, $to) = explode("-", $range);

		// if the last-byte-pos is missing or too big, go to the end
		if (empty($to) || $to >= $size) {
			$to = $size - 1;
		}

		// accept the form -500 (final 500 bytes)
		if (empty($from)) {
			$from = max($size - $to, 0);
			$to = $size - 1;
		} else if ($from >= $size)
			requestedrangenotsatisfiable();

		// send 206 Partial content header
		header("Content-Range: bytes ${from}-${to}/${size}", true, 206);
		header("Content-Length: " . ($to - $from + 1));
	} else {
		// full file
		$from = null;
		$to = null;
		header("Content-Length: $size");
	}

	// headers common to full file and range
	lastmodified(filemtime($repo->idToCanonicalPath($id)));
	header("Content-Type: $preferredtype");
	header("Content-Transfer-Encoding: binary");

	// output the data
	if (is_null($from) && is_null($to))
		readfile($repo->idToCanonicalPath($id));
	else {
		$bytesatonce = 4 * 1024;
		$file = fopen($repo->idToCanonicalPath($id), "rb");
		$pos = $from;
		fseek($file, $pos);
		while (!feof($file)) {
			// if client has disappeared abort
			if (connection_aborted())
				break;

			// reset time limit
			set_time_limit(0);

			// output some file
			if (!is_null($to) && $pos + $bytesatonce > $to)
				echo fread($file, $to - $pos);
			else
				echo fread($file, $bytesatonce);

			// flush it to the client
			flush();
		}
		fclose($file);
	}

	exit;
}

// check client has metadata permission
if (!$repo->haveMetadataPermission())
	notauthorized();

// an RDF type
if (in_array($preferredtype, array_keys($rdftypes))) {
	// if we're at the Audiofile URI, redirect to infodoc URI
	if (!$infodoc)
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
	// if we're at the Audiofile URI, redirect to infodoc URI
	if (!$infodoc)
		redirect($repo->getURIPrefix() . $id . "_", 303);

	// load Graphite
	require_once "lib/arc2/ARC2.php";
	require_once "lib/Graphite/graphite/Graphite.php";

	// load graph
	$graph = new Graphite($ns);
	$graph->addRDFXML($repo->getURIPrefix(), $repo->getRDF($id));

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
		<p>You have this HTML info document because according to your 
		<code>Accept</code> header it is your preferred format of those offered. 
		The available formats for this info document are</p>
		<ul>
			<?php foreach ($types as $type) { ?>
				<li><code><?php echo htmlspecialchars($type); ?></code></li>
			<?php } ?>
		</ul>
		<?php if (!is_null($audio_mimetype)) { ?>
			<p>The <a href="<?php echo $repo->getURIPrefix() . $id; ?>">Audiofile resource</a> 
			(which you may have been redirected from) is additionally available 
			as</p>
			<ul>
				<li><code><?php echo htmlspecialchars($audio_mimetype); ?></code></li>
			</ul>
		<?php } ?>
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

// something has gone wrong
servererror("got to the end of the webrequest script -- shouldn't be there");

?>
