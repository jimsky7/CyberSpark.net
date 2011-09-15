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

// e-Mail formatting
define('INDENT', "          ");

define('DEFAULT_NOTIFY_HOUR', "23");				// yes, a string
define('DEFAULT_SOCKET_TIMEOUT', 60);				// seconds
define('DEFAULT_LOOP_TIME', 30);					// minutes

// Google Safe Browsing service - note this is an IP address and a strange port
//   because we don't advertise this server in DNS and we don't use a common port.
//   It is, however, a web (HTTP) service.
define('GSB_SERVER', "http://0.0.0.0:4040");		// NO ending "/" please!
define('MAX_GSB_DEPTH', 2);							// "legacy" variable
define('GSB_DEPTH_MAX', 2);							// overall maximum GSB spidering depth
define('GSB_DEPTH_1', 1);							// GSB spider only the links off the main page
define('GSB_DEPTH_2', 2);							// GSB the main page, links from it, and links from those pages

// Paths
// Set an absolute path to the APP directory
define('APP_PATH', "/usr/local/cyberspark/");			// MUST HAVE ENDING "/" to work
// OR use the line below (without comment //'s) to set it 'automatically' within the app, 
//    based on the executing file.
// $path 		= substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// includes a trailing "/"
// Subdirectories
define('PROPS_DIR', "properties/");				// where properties files live
define('PROPS_EXT', ".properties");				// extension for properties files
define('DATA_DIR', "data/");						// where data will live
define('DATA_EXT', ".db");						// extension for database files
define('MAX_DATA_SIZE', 10000000);				// maximum 'store' file size
define('FILTERS_DIR', "filters/");					// where the scanning filters live
define('FILTERS_EXT', ".php");						// extension for filter files
define('LOG_DIR', "log/");						// where the csv logs will be written
define('LOG_EXT', ".log");						// extension for log files
define('PID_EXT', ".pid");						// extension for process-id files
define('HEARTBEAT_EXT', ".next");				// extension for heartbeat files
define('HEARTBEAT_LATE',60);					// number of seconds before heartbeat is "late"

// cybersparkd.php items
define('PID_FILESIZE_LIMIT', 100);			// max length of a PID file
define('SHUTDOWN_WAIT_TIME', 500000);		// time to wait during shutdown (microseconds)
define('FAILURE_ALERT_MAX', 5);				// how many alerts cybersparkd should send on process failure
define('KEEPALIVE_LOOP_SLEEP_TIME', 60);	// in seconds
define('RESTART_ON_FAILURE', true);			// if true, then cybersparkd will restart failed processes

// cyberspark.php items
// NO CR AFTER THE CLOSING OF PHP, PLEASE. IMPORTANT!
?>