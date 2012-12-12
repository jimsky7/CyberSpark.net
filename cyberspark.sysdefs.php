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
// This file contains universal CyberSpark constants that do not vary from one locale
//   to another. Email addresses and login information are now in cyberspark.config.php
//   as of 2012-12-12 (sky@cyberspark.net)
//

if (!defined('DEFAULT_IDENTITY')) {
	define('DEFAULT_IDENTITY', "CyberSpark Version 4.20121212=PHP+SSL http://cyberspark.net/agent;");
}
if (!defined('DEFAULT_USERAGENT')) {
	define('DEFAULT_USERAGENT', "Mozilla/5.0 (compatible; MSIE 8.0; CyberSpark Version 4.20121212=PHP+SSL http://cyberspark.net/agent;) Ubuntu/12.04");
}

// e-Mail formatting
define('INDENT', "          ");

define('DEFAULT_NOTIFY_HOUR', "23");				// yes, a string
define('DEFAULT_SOCKET_TIMEOUT', 60);				// seconds
define('DEFAULT_LOOP_TIME', 30);					// minutes

// Google Safe Browsing service - note this is an IP address and a strange port
//   because we don't advertise this server in DNS and we don't use a common port.
//   It is, however, a web (HTTP) service.
// define('GSB_SERVER', "http://0.0.0.0:4040");		// NOTE this is in sysdefs now
define('MAX_GSB_DEPTH', 2);							// "legacy" variable
define('GSB_DEPTH_MAX', 2);							// overall maximum GSB spidering depth
define('GSB_DEPTH_1', 1);							// GSB spider only the links off the main page
define('GSB_DEPTH_2', 2);							// GSB the main page, links from it, and links from those pages
define('GSB_PAGE_SIZE_LIMIT', 500000);					// largest page size we will check - due to DOM bugs

// Paths to subdirectories of APP_PATH (which is defined in cyberspark.config.php)
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
define('HEARTBEAT_BLUE',1800);					// number of seconds before late heartbeat causes code blue (30 minutes)
define('URL_EXT', ".url");						// extension for 'URL' files
define('MAX_URL_LENGTH', 2083);						// same as MSIE, though there is no IETF limit

// cybersparkd.php items
define('PID_FILESIZE_LIMIT', 100);			// max length of a PID file
define('SHUTDOWN_WAIT_TIME', 500000);		// time to wait during shutdown (microseconds)
define('FAILURE_ALERT_MAX', 5);				// how many alerts cybersparkd should send on process failure
define('KEEPALIVE_LOOP_SLEEP_TIME', 250);	// in seconds
define('RESTART_ON_FAILURE', true);			// if true, then cybersparkd will restart failed processes

// cyberspark.php items
// NO CR AFTER THE CLOSING OF PHP, PLEASE. IMPORTANT!
?>