<?php
// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

////////////////////////////////////////////////////////////////////////
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

////////////////////////////////////////////////////////////////////////
// Sometimes cURL error symbolics are not defined, so be sure we have them.
if (!defined('CURLE_OPERATION_TIMEDOUT')) {
	define ('CURLE_OPERATION_TIMEDOUT', 28);
}
if (!defined('CURLE_RECV_ERROR')) {
	define ('CURLE_RECV_ERROR', 56);
}

/* Do not close the php */