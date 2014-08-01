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

// Define the "API KEY" that will be used for authentication to the log+analysis subsystem.
define ('CS_API_KEY',	'your_API_KEY_here');		// MUST MATCH in cs-log-pw.php on analysis system

/* Do not close the php */