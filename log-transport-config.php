<?php
// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

define ('CS_URL_POST',  	'https://server.example.com/analysis/cs-log-post-entry.php');	// put one log entry into database
define ('CS_LOCK_POST', 	'https://server.example.com/analysis/cs-lock-handler.php');		// lock for suppressed email CS_LOCK_POST
define ('CS_SUPPRESS_URL', 	'https://server.example.com/cs-suppress.php');	    			// control suppressed email

define ('DEBUG', false);

// **** Do not close the PHP here, leave it open...
