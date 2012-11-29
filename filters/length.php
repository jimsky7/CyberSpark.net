<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: length
		Records the length of a page. Alerts if the
		length changes since the last time it was observed.
		Keeps a list of the lengths it sees and only reports
		each one the first time it is seen.  This way pages
		that vary by a few bytes and then return to usual size
		do not get repeatedly flagged.

		NOTE: THIS IS EXACTLY THE SAME CODE AS 'checklength' - DUPLICATE FILE with different filter name
	*/

// To make a plugin:
// Create a function with the same name as the file (but not the extension).
//   This function will be called once when the main script has read parameters
//   but has not yet performed a scan.  This function should register a callback
//   which will become its "filter."
//   This function may install callbacks for any of these events
//     addFilter($name, 'init', 'callback', $rank)  	// called once when main script initialized
//     addFilter($name, 'scan', 'callback', $rank)  	// called for each URL scanned
//     addFilter($name, 'destroy', 'callback', $rank)	// called before main script shuts down
// The callback function gets 3 args when it is called:
//  $content
//    The content of the URL being spidered.  Could be null if the callback
//      is for an 'init' or 'destroy' event
//  $args
//    which is an asociative array containing various arguments/parameters from
//    the main script.
//    ['oncedaily'] if isset() then perform tasks that should only be performed
//      infrequently (only once or twice a day)
//    ['code'] the result code of the HTTP request - might indicate failure of an
//      GET (such as a 404 result) in which case $content will not be set.
//    ['url'] the URL that is being checked
//    ['conditions'] any conditions for this URL from the properties file
//	  ['verbose'] if caller wants lots of info rather than less info
//  $privateStore
//    Private persistent storage maintained on behalf of the plugin and also preserved between
//      executions of the main script.  This is persistent storage for the plugin.
//    The callback returns three results - the first is a message to be displayed or 
//      included in an email, the second indicates the 'type' of alert generated,
//      and the third is an associative array which will be preserved and
//      passed in again as the argument "$store"

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";

function lengthScan($content, $args, $privateStore) {
	$filterName = "length";
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

function lengthInit($content, $args, $privateStore) {
	$filterName = "length";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Checking length of " . $args['url'];
	$result   = "OK";
//echo "filter 'init' 'length'\n";	
	return array($message, $result, $privateStore);
	
}

function lengthDestroy($content, $args, $privateStore) {
	$filterName = "length";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

function length($args) {
	$filterName = "length";
 	if (!registerFilterHook($filterName, 'scan', 'lengthScan', 10)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', 'lengthInit', 10)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', 'lengthDestroy', 10)) {
		echo "The filter '$filterName' was unable to add a 'Destroy' hook. \n";	
		return false;
	}
	return true;
}

?>