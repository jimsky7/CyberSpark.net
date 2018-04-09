<?php
include('cs-log-config.php');			// must be in current directory
session_start();
$_SESSION[SESSION_AUTH_USER] = true;	// NO QUOTES ! This is a defined string
		// Means user is HTTP authenticated
		// We know it just because this page is running.
		// I'd prefer to use $_SERVER['REMOTE_USER'] but
		// it is not reliably set.
?><html>
<head>
    <meta charset='utf-8' />
	<meta http-equiv="refresh" content="0; url=index-bubbles.php">
</head>
<body>
</body>
</html>