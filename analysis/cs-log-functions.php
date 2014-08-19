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

/* Do not close the php */