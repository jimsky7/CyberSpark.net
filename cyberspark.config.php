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
// Note: Each 'properties' file may specify SMTP info for outbound alerts. If a
//       perticular properties file is going to use a DIFFERENT SMTP than the others
//       (e.g. you're using a third party SMTP for just one specific properties file)
//       then you should add 'replyto' and 'abuseto' properties to that specific
//       properties file. Examples:
//       	replyto=x@example.com
//       	abuseto=x@example.com
//  The defaults below will be used unless you override them in the properties file(s).
define('EMAIL_REPLYTO', 'email@example.com');		// MUST USE NO quotes or <> brackets
define('EMAIL_ABUSETO', 'email@example.com');		// MUST USE NO quotes or <> brackets

/////////////////////////////////////////////////////////////////////////////////
// If you set SSL_FILTER_REQUIRE_EXPLICIT_OK to true, the 'ssl' filter looks for a definitive
//   "OK" result and if it's not there, you'll get a report saying there's a problem.
// If you set SSL_FILTER_REQUIRE_EXPLICIT_OK to false, then the filter only considers certain
//   explicit error conditions, and may let some unanticipated errors slip through.
//   As long as the checker returns "OK" status in its messages, we consider the
//   cert to be OK.
// (Recommended default setting is TRUE.)
if (!defined('SSL_FILTER_REQUIRE_EXPLICIT_OK')) {
	define ('SSL_FILTER_REQUIRE_EXPLICIT_OK', true);
}

/////////////////////////////////////////////////////////////////////////////////
// If you have a GSB server, or if CyberSpark gave you the IP address to use theirs,
//   insert the URL for the threatMatches:find method and your Google API key.
define('GSB_SERVER', 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=GSB_API_KEY');
define('GSB_API_KEY', '');						// insert your Google API key

/////////////////////////////////////////////////////////////////////////////////
// If you have arranged access to an ASN locator service, put the full URL below
//   This must be compatible with team-cymru API
//	 See the ASN filter source file for more information.
define('ASN_SERVER', "https://asn.cymru.com/cgi-bin/whois.cgi");

/////////////////////////////////////////////////////////////////////////////////
// If you have arranged access to GEO IP locator service, put the full URL below
//   This must be compatible with FREEGEOIP API - see http://freegeoip.net/
//   See the GEO filter source file for more information
// Note that freegeoip.net provides a free public service up to 15,000 hits an hour
define('GEO_SERVER', "https://freegeoip.net/json/");

/////////////////////////////////////////////////////////////////////////////////
// Paths
// Set a path to the APP directory
define('APP_PATH', '/usr/local/cyberspark/');			// MUST HAVE AN ENDING "/" to work
// The app itself will try to figure out where it is and override this value.

// NO CR AFTER THE CLOSING OF PHP, PLEASE. IMPORTANT!
?>