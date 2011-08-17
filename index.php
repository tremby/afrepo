<?php

require_once "setup.php";
$base = parse_url($repo->getURIPrefix());
$basepath = $base["path"];

?>
<!DOCTYPE HTML>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title><?php echo htmlspecialchars($repo->getName()); ?></title>
	</head>
	<body>
		<h1><?php echo htmlspecialchars($repo->getName()); ?></h1>
		<p>Requests are of the form
		<code><?php echo htmlspecialchars($basepath); ?><em>audiofile-id</em></code>
		where <em>audiofile-id</em> is the 32-digit hexidecimal identifier of 
		the audiofile.</p>
		<p>The <code>Accept</code> header you send is taken into account. 
		Various types of RDF are available, plus an HTML representation and 
		potentially the audio data (use <code>audio/*</code> to accept any 
		format of audio).</p>
		<?php if ($repo->getSparqlEndpoint()) { ?>
			<p>A Sparql endpoint is available at
			<code><?php echo htmlspecialchars($repo->getSparqlEndpoint()); ?></code>.</p>
		<?php } ?>
	</body>
</html>
