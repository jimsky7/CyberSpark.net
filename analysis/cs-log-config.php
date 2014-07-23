<?php
// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

define ('DEBUG', false);

// Your MySQL user information
define ('MYSQL_HOST', 'localhost');
define ('MYSQL_USER', 'cybersparkUSER');
define ('MYSQL_PASSWORD', 'blurk-spork#mqxin-425748');
define ('MYSQL_DATABASE', 'cyberspark_analysis');
define ('MYSQL_ERROR_DUPLICATE', 1062);

// Files and scripts used in analysis
// Note: These may reside in a subdirectory on your web server and you do not need to explicitly
//       specify the subdirectory here, as long as they're all in the same subdirectory. If some are
//       elsewhere, you may adjust these to include the proper 'subdirectories' as they'd appear in the URL.
//       (I suppose you could even have them on a different server...)
define ('CS_URL_CONFIG',       'cs-log-config.php');				// configuration file (this file)
define ('CS_URL_FILE_PROCESS', 'cs-log-file-process.php');		// log file processor
define ('CS_URL_GET',          'cs-log-get-entries.php');		// get CSV or TSV of log entries
define ('CS_URL_FROM_HASH',    'cs-log-get-url-from-hash.php');	// given a HASH, return the actual URL
define ('CS_URL_POST',   	   'cs-log-post-entry.php');			// put one log entry into database

// Other definitions
define ('PAD_VALUE', 3600);				// value to add when padding (3600 == one hour)
define ('PAD_CODE', 222);				// HTTP result code to insert when padding
define ('SAVE_MESSAGES', true);			// causes messages to be saved in table `messages` //need lots of storage

if (!function_exists('ifGetOrPost')) {
	function ifGetOrPost($name) {
	if (isset($_GET[$name])) {
		return $_GET[$name];
	}
	if (isset($_POST[$name])) {
		return $_POST[$name];
	}
	return null;
	}
}

/* Do not close the php */