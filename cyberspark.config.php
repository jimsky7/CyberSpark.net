<?php

////////////////////////////////////////////////////
// include this file near the top of each of these two main files. No others.
// 	cyberspark.php
// 	cybersparkd.php
//
// PUT NOTHING IN HERE THAT WOULD EMIT HTML OR TEXT
// Include only PHP language statements for configuration purposes.
// And additionally it would be best if there were nothing executable, or even
//   any variables declared herein. Just constants, if you please.
//
// This file is LOCALIZED FOR YOU ONLY
// It is the place to put email names and passwords, and addresses to notify. All universal
//   CyberSpark constants are instead in cyberspark.sysdefs.php (as of 2012-12-11)
//

// Process-related variables and constants
define('INSTANCE_ID', "CS8");							// "serial number" for this Cyberspark instance

define('DEFAULT_IDENTITY', "CyberSpark Version 4.01=PHP+SSL 20110207 http://cyberspark.net/agent;");
define('DEFAULT_USERAGENT', "Mozilla/5.0 (compatible; MSIE 8.0; CyberSpark Version 4.01=PHP+SSL 20110207 http://cyberspark.net/agent;) Ubuntu/10.04");

// SMTP-related variables
// Yes, you have to put your user name and password somewhere so that
//   PHP can get it, so might as well put it here.
define('SMTP_SERVER', "ssl://smtp.yourdomain.here");		// default SMTP server
define('SMTP_PORT', 465);							// port for SMTP
define('SMTP_USER', "aaa@bbb.net");			// default SMTP user
define('SMTP_PASSWORD', "xxxxxxxx");				// default SMTP password
// e-Mail default addresses
define('EMAIL_FROM', "\"Your name\" <email@yourdomain.here>");
// notifications from cybersparkd.php - child processes cannot override this
define('EMAIL_ADMINISTRATOR', "email@yourdomain.here");
// notifications from child processes (cyberspark.php "monitors") - properties files can override
define('EMAIL_TO', "email@yourdomain.here");					
// Items for email headers
define('EMAIL_REPLYTO', "email@yourdomain.here");		// MUST BE NO quotes or <> brackets
define('EMAIL_ABUSETO', "email@yourdomain.here");		// MUST BE NO quotes or <> brackets

// If you have a GSB server, or if CyberSpark gave you the IP address to use theirs,
//   insert the actual IP address here instead of "0.0.0.0"
define('GSB_SERVER', "http://0.0.0.0:4040");		// NO ending "/" please!

// Paths
// Set a path to the APP directory
define('APP_PATH', "/usr/local/cyberspark/");			// MUST HAVE ENDING "/" to work
// The app itself will try to figure out where it is and override this value.

// NO CR AFTER THE CLOSING OF PHP, PLEASE. IMPORTANT!
?>