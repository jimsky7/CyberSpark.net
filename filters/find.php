<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: find
		This filter is always applied to all URLs.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/


// CyberSpark system variables, definitions, declarations
global $path;
include_once $path."cyberspark.config.php";
include_once $path."include/echolog.php";

function findScan($content, $args, $privateStore) {
	$filterName = "find";
	$result   = "OK";						// default result
	$url = $args['url'];
	$message = '';
	$contentLength = strlen($content);
	$conditions = $args['conditions'];		// This is the raw set of conditions from properties file
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	if (strpos($conditions, '"') !== false) {
		$i = strpos	($conditions, '"');
		$j = strpos	($conditions, '"', $i+1);
		$key = substr($conditions, $i+1, $j-1);		
		if (stripos($content, $key) !== false) {
			$result = "Alert";
			$message = "The value \"$key\" was found on this page or file.\n";
		}
		else {
			// String not found. Return a message in case it's the 'notify' hour.
			$message = "The value \"$key\" was not found in this page or file.\n";
		}
	}
	else {
		// No search requested. Return a message in case it's the 'notify' hour.
		$message .= "('find' was not requested)\n";
	}
	
	$message = trim($message , "\n");				// remove any trailing LF
	return array($message, $result, $privateStore);
}

function findInit($content, $args, $privateStore) {
	$filterName = "find";
	// $content is the CONTENT returned from the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	//    The actual URL is in $args['url']
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Initialized. URL is " . $args['url'];
	$result   = "OK";

	return array($message, $result, $privateStore);
	
}

function findDestroy($content, $args, $privateStore) {
	$filterName = "find";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

function find($args) {
	$filterName = "find";
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