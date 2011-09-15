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
	//   Looks for " 00:" (where 00 is the hour) in the current time string
	return (strpos(date("r"), (" $notify:")) !== false);
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
		if (strncmp(strtolower($result), "http://", 7) == 0) {
			$result = substr($result, 7);
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
		if (strncmp(strtolower($result), "http://", 7) == 0) {
			$result = substr($result, 7);
			// Truncate to remove everything after slash
			$slashPos = stripos($result, "/");
			if ($slashPos !== false) {
				$result = substr($result, 0, $slashPos);
			}
			// Find next-to-last dot
			$dotArray = explode(".", $result);
			$ct = count($dotArray);
			if ($ct > 2) {
				$result = $dotArray[$ct-2] . "." . $dotArray[$ct-1];
			}
		}
	}
	catch (Exception $x) {
	}
	return $result;
}


?>