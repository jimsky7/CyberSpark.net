<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: checklength
		Records the length of a page. Alerts if the
		length changes since the last time it was observed.
		Keeps a list of the lengths it sees and only reports
		each one the first time it is seen.  This way pages
		that vary by a few bytes and then return to usual size
		do not get repeatedly flagged.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "include/echolog.inc";

function checkLengthScan($content, $args, $privateStore) {
	$filterName = "checklength";
	$result   = "OK";						// default result
	$url = $args['url'];
	$contentLength = strlen($content);
	$message = "";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	// See whether length has changed since last time
	if (isset($privateStore[$filterName][$url])) {
		// This URL has been seen before
		
		$lengthsString = $privateStore[$filterName][$url]['lengths'];
		$lengths = explode(",", $lengthsString);
		$lengthMatched = false;
		foreach ($lengths as $oneLength) {
			if ($contentLength == (int)$oneLength) {
				// Current length matches a previous length
				$lengthMatched = true;
				break;
			}
		}
		
		
		if (!$lengthMatched) {
			// Changed
			$message .= "Length changed to " . $contentLength . "\n";
			$message .= INDENT . "Previous lengths include [" . $lengthsString . "]";
			$result = "Changed";
			$lengthsString .= "," . (string)$contentLength;
		}
		else {
			// No change
		}
	}
	else {
		// This URL has not been seen before
		$lengthsString = (string)$contentLength;
	}
	// Record the length of this URL
	$privateStore[$filterName][$url]['lengths'] = $lengthsString;
	return array($message, $result, $privateStore);
	
}

function checkLengthInit($content, $args, $privateStore) {
	$filterName = "checklength";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Initialized. URL is " . $args['url'];
	$result   = "OK";
//echo "filter 'init' 'checklength'\n";	
	return array($message, $result, $privateStore);
	
}

function checkLengthDestroy($content, $args, $privateStore) {
	$filterName = "checklength";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

function checklength($args) {
	$filterName = "checklength";
 	if (!registerFilterHook($filterName, 'scan', $filterName.'Scan', 10)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', $filterName.'Init', 10)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', $filterName.'Destroy', 10)) {
		echo "The filter '$filterName' was unable to add a 'Destroy' hook. \n";	
		return false;
	}
	return true;
}

?>