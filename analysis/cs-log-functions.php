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
if (!function_exists('hoursAndMinutes')) {
	function hoursAndMinutes($seconds) {
		$sTime = '';
		// Hours
		$sHours = (int)($seconds/(60*60));
		if ($sHours > 0) {
			$sTime  .= $sHours.' hour'.($sHours>1?'s':'');
		}
		// and minutes
		$sMinutes = (int)(($seconds/60)-($sHours*60));
		if ($sMinutes > 0) {
			$sTime .= ' and '.$sMinutes.' minute'.($sMinutes>1?'s':'');
		}
		return $sTime;
	}
}

////////////////////////////////////////////////////////////////////////
// Sometimes cURL error symbolics are not defined, so be sure we have them.
// Errors related to timing out or failing to resolve
if (!defined('CURLE_OPERATION_TIMEDOUT')) {
	define ('CURLE_OPERATION_TIMEDOUT', 28);
}
// Possibly intercept these in the future
// CURLE_COULDNT_RESOLVE_HOST'

// Errors related to connecting or transmission
if (!defined('CURLE_RECV_ERROR')) {
	define ('CURLE_RECV_ERROR', 56);
}
// Possibly intercept these in the future
// CURLE_COULDNT_CONNECT
// CURLE_FTP_WEIRD_SERVER_REPLY
// CURLE_READ_ERROR
// CURLE_SSL_CONNECT_ERROR
// CURLE_SSL_PEER_CERTIFICATE
/// CURLE_GOT_NOTHING

/* Do not close the php */