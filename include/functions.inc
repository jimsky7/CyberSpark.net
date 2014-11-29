<?php
	/**
		CyberSpark.net monitoring-alerting system
		some general utility functions used multiple places
	*/


///////////////////////////////// 
// Check to see if we are in the 'notify' hour
// Just uses the current time - so it might not exactly match the time
//	a scan is being performed because they can take considerable time.
function isNotifyHour($notify) {
	// Returns 'true' if we are in the "notify" hour
	// date "H" gives us hour with leading zeros (00 thru 23)
	// (Note quote in order to ensure string for comparison)
	return (date("H")=="$notify");
}

///////////////////////////////// 
// Check an array of 'conditions' to see if this one is present
function isACondition($url, $condition) {
	$conditionsArray = $url->conditionsArray;
	if (isset($conditionsArray) && is_array($conditionsArray) && count($conditionsArray)) {
		return in_array($condition, $conditionsArray);
	}
	else {
		return false;
	}
}

///////////////////////////////// 
// Put " quotes around a string after transforming any internal quotes that it contained
function safeQuote($s) {
	if (isset($s) && (strlen($s)>0)) {
		return "\"" . str_replace("\"", "'", $s) . "\"";	
	}
	else {
		return "";
	}
}

///////////////////////////////// 
// Remove \r \n \t and "<br>" from a string
function flatten($s) {
	if (isset($s)) {
		return str_replace(array("\r", "\n", "\t", "<br>")," ", $s);
	}
	else {
		return $s;
	}	
}

///////////////////////////////// 
// Remove double-blanks from a string
function condenseBlanks($s) {
	if (isset($s)) {
		return preg_replace('/\s+/',' ', $s);
	}
	else {
		return $s;
	}	
}

///////////////////////////////// 
function fqdnOnly($url) {
	$result = str_replace(" ", "", $url);
	try {
		$i = strpos($result, '://');
		if ($i > 0) {
			$result = substr($result, $i+3);
			// Truncate to remove everything after slash
			$slashPos = stripos($result, "/");
			if ($slashPos !== false) {
				$result = substr($result, 0, $slashPos);
			}
		}
	}
	catch (Exception $x) {
	}
	return $result;
}

///////////////////////////////// 
function domainOnly($url) {
	$result = str_replace(" ", "", $url);
	try {
		// Remove 'protocol://'
		$i = strpos($result, '://');
		if ($i !== false) {
			$result = substr($result, $i+3);
		}
		// Truncate to remove everything after remaining slash
		$i = stripos($result, "/");
		if ($i !== false) {
			$result = substr($result, 0, $i);
		}
		// Remove 'name:password@' from front
		$i = strpos($result, '@');
		if ($i !== false) {
			$result = substr($result, $i+1);
		}
		// Remove ':port' from end
		$i = strpos($result, ':');
		if ($i !== false) {
			$result = substr($result, 0, $i);
		}
	}
	catch (Exception $x) {
	}
	return $result;
}

///////////////////////////////// 
function lowercaseFQDN($url) {
	// Convert the FQDN (only) to lower case, leaving the rest of the protocol and URL alone.
	$result = $url;
	$candidate = str_replace(" ", "", $url);
	try {
		// HTTP://WWW.DOMAIN.COM/abc.php
		// 01234567890123456789012345678
		$i = strpos($candidate, '://');							// 4
		if ($i >= 0) {
			$j = strpos($candidate, '/', $i+3);					// 21
			if ($j > 0) {
				// Terminating '/' found - convert to lowercase, then add the rest of the URL
				$result =  substr($candidate, 0, $j+1);			// HTTP://WWW.DOMAIN.COM/
				$result =  strtolower($result);					// http://www.domain.com/
				$result .= substr($candidate, $j+1);			// http://www.domain.com/abc.php
			}
			else {
				// No terminating '/' so must lowercase everything - it's just a domain
				$result = strtolower($candidate);
			}
		}
	}
	catch (Exception $x) {
	}
	return $result;	
}

///////////////////////////////// 
if (!defined('ROUND_TO_MILLISECONDS')) { define ('ROUND_TO_MILLISECONDS', 3);         }	// usually definded in cyberspark.sysdefs.php
if (!defined('ROUNDTIME'))             { define ('ROUNDTIME', ROUND_TO_MILLISECONDS); } 	// usually definded in cyberspark.sysdefs.php

function roundTime($time, $roundtime=ROUNDTIME) {
	return round($roundTime);
}

///////////////////////////////// 
// hostname()
// Given a URL, find the hostname
function hostname($url) {
	$result = str_replace(" ", "", $url);
	try {
		// Remove 'protocol://'
		$i = strpos($result, '://');
		if ($i !== false) {
			$result = substr($result, $i+3);
		}
		// Truncate to remove everything after remaining slash
		$i = stripos($result, "/");
		if ($i !== false) {
			$result = substr($result, 0, $i);
		}
		// Remove 'name:password@' from front
		$i = strpos($result, '@');
		if ($i !== false) {
			$result = substr($result, $i+1);
		}
		// Remove ':port' from end
		$i = strpos($result, ':');
		if ($i !== false) {
			$result = substr($result, 0, $i);
		}
	}
	catch (Exception $x) {
	}
	return $result;
}

///////////////////////////////// 
// getIPforHost()
// Given a URL, find the IP address of the host
function getIPforHost($url) {
	$s = hostname($url);	
	$dnsInfo = dns_get_record($s, DNS_A);
	if ($dnsInfo === false) {
		$dnsInfo = dns_get_record($s, DNS_AAAA);
	}
	if ($dnsInfo !== false && count($dnsInfo) > 0) {
		// Use only the first record returned
		$dnsInfo = $dnsInfo[0];	
		// Return the IP associated with the hostname
		if ($dnsInfo['type'] == 'A' || $dnsInfo['type'] == 'AAAA') { 
			if (isset($dnsInfo['ip'])) {
				return $dnsInfo['ip'];
			}
		}
	}
	// Return null on failure
	return null;		
}

?>