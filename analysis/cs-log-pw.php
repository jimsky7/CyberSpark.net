<?php
// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

// HTTP "Basic authentication"
// Define these ONLY if you need HTTP authentication to access the /analysis/ server pages.
//   i.e. comment them out if you do not need them. The code elsewhere will adapt.
define ('CS_HTTP_USER',	'your_user_name_here');		// define these only if required for HTTP access to analysis directory
define ('CS_HTTP_PASS', 'your_password_here' );		// define these only if required for HTTP access to analysis directory

// Define the "CS_API_KEYS" array that will be used for authentication to the log+analysis subsystem.
// Each sniffer has a single key CS_API_KEY that it presents when log-transport is sending data. 
// It must match one entry in this array. You can define as many keys as you need, such as one per
// sniffer, or you can just use a single entry for all sniffers. The API_KEY is only checked when
// data is being posted to the system, not when retrieving for analysis or graphing.
$CS_API_KEYS = array(
	'oooooooo',
	'ooooooo3',
	'ooooooo4',
	'ooooooo7',
	'ooooooo8',
	'ooooooo9'
);

// Your MySQL user information
define ('MYSQL_HOST', 		'localhost');
define ('MYSQL_USER', 		'your_mysql_user_name');
define ('MYSQL_PASSWORD',	'your_mysql_password');
define ('MYSQL_DATABASE',	'cyberspark_analysis');
define ('MYSQL_ERROR_DUPLICATE', 1062);

/* Do not close the php */