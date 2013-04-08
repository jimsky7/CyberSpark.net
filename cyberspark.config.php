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
// This file is LOCALIZED FOR AND BY YOU ONLY for your CyberSpark installation.
// It is the place to put email names and passwords, and addresses to notify. 
// All universal CyberSpark constants are instead in cyberspark.sysdefs.php 
//   (as of 2012-12-11)
//

// Process-related variables and constants
define('INSTANCE_ID', "CS9");			// ID for this Cyberspark instance ... YOU pick
										// must match in '/etc/init.d/cyberspark'
										// properties file names too 'CS9-0.properties' etc.
define('INSTANCE_LOCATION', 'London');	// default location to be used in notifications {location}

/////////////////////////////////////////////////////////////////////////////////
// SMTP-related variables
// Yes, you have to put your user name and password somewhere so that
//   PHP can get it, so might as well put it here.
define('SMTP_SERVER', 'ssl://smtp.example.com');		// default SMTP server
define('SMTP_PORT', 465);								// port for SMTP
define('SMTP_USER', 'email@example.com');				// default SMTP user
define('SMTP_PASSWORD', 'xxxxxxxx');					// default SMTP password

/////////////////////////////////////////////////////////////////////////////////
// e-Mail default addresses. These can be overridden in the 'properties' files for each
// of the sniffers. And they usually are.
define('EMAIL_FROM', '"Your name" <email@example.com>');
// notifications from cybersparkd.php - child processes cannot override this
define('EMAIL_ADMINISTRATOR', 'email@example.com');
// notifications from child processes (cyberspark.php "sniffers")
//  - properties files can override
define('EMAIL_TO', 'email@example.com');					
// Items for email headers
define('EMAIL_REPLYTO', 'email@example.com');		// MUST USE NO quotes or <> brackets
define('EMAIL_ABUSETO', 'email@example.com');		// MUST USE NO quotes or <> brackets

/////////////////////////////////////////////////////////////////////////////////
// If you have a GSB server, or if CyberSpark gave you the IP address to use theirs,
//   insert the actual IP address here instead of "0.0.0.0" -- this is used in the GSB-
//   related filters. The CyberSpark GSB implementation listens only on port 4040.
define('GSB_SERVER', 'http://0.0.0.0:4040');		// NO ending "/" please!

/////////////////////////////////////////////////////////////////////////////////
// Paths
// Set a path to the APP directory
define('APP_PATH', '/usr/local/cyberspark/');			// MUST HAVE AN ENDING "/" to work
// The app itself will try to figure out where it is and override this value.

// NO CR AFTER THE CLOSING OF PHP, PLEASE. IMPORTANT!
?>