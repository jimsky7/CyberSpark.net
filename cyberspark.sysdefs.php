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

/////////////////////////////////////////////////////////////////////////////////
// User-Agent and other identity to be presented by CyberSpark when sniffing using HTTP.
define('CYBERSPARK_VERSION', '4.20171207');
if (!defined('DEFAULT_IDENTITY')) {
	// Please use your own URL in this string (in place of cyberspark.net/agent)
	define('DEFAULT_IDENTITY', 'CyberSpark Version '.CYBERSPARK_VERSION.' http://cyberspark.net/agent');
}
if (!defined('DEFAULT_USERAGENT')) {
	// Replace "Ubuntu..." with your server OS
	define('DEFAULT_USERAGENT', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:33.0) '.DEFAULT_IDENTITY.' Ubuntu/16.04');
	// Please use your own URL if you choose the one below instead of the one above
//	define('DEFAULT_USERAGENT', "Mozilla/5.0 (compatible; MSIE 8.0; CyberSpark Version ".CYBERSPARK_VERSION." http://cyberspark.net/agent;) Ubuntu/16.04 LTS");
}

/////////////////////////////////////////////////////////////////////////////////
// e-Mail formatting
define('INDENT', "          ");

/////////////////////////////////////////////////////////////////////////////////
if (!defined('DEFAULT_NOTIFY_HOUR')) {
	define('DEFAULT_NOTIFY_HOUR', "23");				// yes, a string
}
if (!defined('DEFAULT_SOCKET_TIMEOUT')) {
	define('DEFAULT_SOCKET_TIMEOUT', 60);				// seconds
}
if (!defined('DEFAULT_LOOP_TIME')) {
	define('DEFAULT_LOOP_TIME', 30);					// minutes
}

if (!defined('DEFAULT_DNS_POOL_EXPIRE_MINUTES')) {
	define('DEFAULT_DNS_POOL_EXPIRE_MINUTES', 1440);					// 24 hours (in minutes)
}

// Define the names of the filters that are 'basic' - i.e. they are to be run even if
//   a URL returns an error or no body.
$BASIC_FILTERS = array('basic', 'asn', 'geo', 'dns');

// If this file is present, it is a message left over from starting up the CyberSpark
// service, and it needs to be sent to a system administrator.
define('STARTUP_MESSAGE_FILE', 'important_startup_message.txt');

/////////////////////////////////////////////////////////////////////////////////
// Google Safe Browsing service - note this is an IP address and an unusual port
//   because we don't advertise this server in DNS and we don't use a common port.
//   It is, however, a web (HTTP) service.
// define('GSB_SERVER', "http://0.0.0.0:4040");	// NOTE this is in cyberspark.config.php now
define('MAX_GSB_DEPTH', 2);					// "legacy" variable
define('GSB_DEPTH_MAX', 2);					// overall maximum GSB spidering depth
define('GSB_DEPTH_1', 1);					// GSB spider only the links off the main page
define('GSB_DEPTH_2', 2);					// GSB the main page, links from it, and links from those pages
define('GSB_PAGE_SIZE_LIMIT', 500000);		// largest page size we will check - due to DOM bugs

/////////////////////////////////////////////////////////////////////////////////
// Paths to subdirectories of APP_PATH (which is defined in cyberspark.config.php)
// PROPERTIES
define('PROPS_DIR', 'properties/');			// where properties files live
define('PROPS_EXT', '.properties');			// extension for properties files
define('PROPS_UNIQUE_COPY', false);			// make TRUE if you want props sent by email to have unique name
// DATABASE
define('DATA_DIR', 'data/');				// where data will live
define('DATA_EXT', '.db');					// extension for database files
define('MAX_DATA_SIZE', 10000000);			// maximum 'store' file size
// FILTERS
define('FILTERS_DIR', 'filters/');			// where the scanning filters live
define('FILTERS_EXT', '.php');				// extension for filter files
// LOG FILES
define('LOG_DIR', 'log/');					// where the csv logs will be written
define('LOG_EXT', '.log');					// extension for log files
define('LOG_TRANSPORT', 'log-transport.php');	// name of log transport PHP file
// ZIP FILES
define('ZIP_EXT', '.gz');					// extension for gzipped files
// PID and heartbeat and 'last url' files
define('PID_EXT', '.pid');					// extension for process-id files
define('HEARTBEAT_EXT', '.next');			// extension for heartbeat files
define('HEARTBEAT_LATE',120);				// seconds before heartbeat is "late" (allowed more slack 2013-11)
define('HEARTBEAT_BLUE',1800);				// secs before late heartbeat causes code blue (30 minutes)
define('URL_EXT', '.url');					// extension for 'URL' files
define('MAX_URL_LENGTH', 2083);				// same as MSIE, though there is no IETF limit

/////////////////////////////////////////////////////////////////////////////////
// cybersparkd.php items
define('PID_FILESIZE_LIMIT', 100);			// max length of a PID file
define('SHUTDOWN_WAIT_TIME', 500000);		// time to wait during shutdown (microseconds)
define('FAILURE_ALERT_MAX', 5);				// how many alerts cybersparkd should send on process failure
define('KEEPALIVE_LOOP_SLEEP_TIME', 90);	// in seconds (best if equal to HEARTBEAT_LATE or shorter)
define('RESTART_ON_FAILURE', true);			// if true, then cybersparkd will restart failed processes

/////////////////////////////////////////////////////////////////////////////////
// rounding of server response time seconds in reports
if (!defined('ROUND_TO_SECONDS'))      { define ('ROUND_TO_SECONDS',      0);         }
if (!defined('ROUND_TO_HUNDREDTHS'))   { define ('ROUND_TO_HUNDREDTHS',   2);         }
if (!defined('ROUND_TO_MILLISECONDS')) { define ('ROUND_TO_MILLISECONDS', 3);         }
if (!defined('ROUND_TO_MICROSECONDS')) { define ('ROUND_TO_MICROSECONDS', 6);         }
if (!defined('ROUNDTIME'))             { define ('ROUNDTIME', ROUND_TO_MILLISECONDS); }

// cyberspark.php items
// NO CR AFTER THE CLOSING OF PHP, PLEASE. IMPORTANT!
?>