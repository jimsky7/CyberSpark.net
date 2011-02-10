<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: basic
		This filter is always applied to all URLs.
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

function basicScan($content, $args, $privateStore) {
	$REFUSAL_TIME = 5;						// connections failing FASTER than this are "refusals"
											// longer than this number will be "timeouts" - in seconds
	$filterName = "basic";
	$result   = "OK";						// default result
	$url = $args['url'];
	$contentLength = strlen($content);
	$elapsedTime = $args['elapsedtime'];
	$httpResult  = $args['httpresult'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.

	if (!isset($httpResult['code'])) {
		// No code means connection failed (could be many reasons)
		$result = "Failed";
		if ($elapsedTime < $REFUSAL_TIME) {
			$message = "Connection failed or was refused within $elapsedTime seconds.";
//echo "[$filtername] HTTP code " . $httpResult['code'] . " HTTP error " . $httpResult['error'] . " time $elapsedTime $url \n"; 
//echo "  JUDGMENT == refused\n";
		}
		else {
			$message = "Connection timed out or failed after $elapsedTime seconds.";
//echo "[$filtername] HTTP code " . $httpResult['code'] . " HTTP error " . $httpResult['error'] . " time $elapsedTime $url \n"; 
//echo "  JUDGMENT == timed out\n";
		}
	}
	else {
		if ($elapsedTime > $args['slow']) {
			$result = "Slow";
			$message = "Slow response after " . $elapsedTime . " seconds.";
//echo "  Slow\n";
		}
		if (($httpResult['code'] != 200) && ($elapsedTime > $args['timeout'])) {
			$result = "Timeout";
			$message = "Timeout after " . $elapsedTime . " seconds.";
//echo "  Timeout\n";
		}
	}
	return array($message, $result, $privateStore);
	
}

function basicInit($content, $args, $privateStore) {
	$filterName = "basic";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Basic filtering";
	$result   = "OK";

	return array($message, $result, $privateStore);
	
}

function basicDestroy($content, $args, $privateStore) {
	$filterName = "basic";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

function basic($args) {
	$filterName = "basic";
 	if (!registerFilterHook($filterName, 'scan', 'basicScan', 1)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', 'basicInit', 1)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', 'basicDestroy', 1)) {
		echo "The filter '$filterName' was unable to add a 'Destroy' hook. \n";	
		return false;
	}
	return true;
}

?>