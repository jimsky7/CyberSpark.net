<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: gsbdaily
		Checks this page against Google Safe Broswing.
		This is the advanced "recursive" code.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "include/echolog.inc";

// IMPORTANT NOTE:
// Some functions required for this filter are in gsb.php and
//   so that filter must be present for this one to work.

///////////////////////////////// 
function gsbdailyScan($content, $args, $privateStore) {
	$filterName = "gsbdaily";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

// Thanks for the DOM suggestion! - see
//   http://w-shadow.com/blog/2009/10/20/how-to-extract-html-tags-and-their-attributes-with-php/

	if (isNotifyHour($args['notify']) && !$privateStore[$filterName][$url]['gsbdailyranalready']) {
	
		// Remove chars that don't make any difference and can get in the way
		$content = str_replace(array("\r","\n","\t"), "", $content);
	
		// Get the pertinent HTML tags
		echoIfVerbose("GSB check \n");
		
		$maxDepth	= MAX_GSB_DEPTH;			// how deep we will spider
		$failures	= 0;						// number of GSB connection failures
		$numberOfChecks = 0;					// number of GSB attempts
		$prefix = "";
		$checkedURLs = array();					// URLs that have been followed already
		$checkedDomains = array();				// domains that have been GSB'd already (including subdirs)
		$howToGetThere= array();			// 'breadcrumbs' for alert message
		
		///////////////////////////////// 
		try {
			// Check the main URL first
//		array_push($howToGetThere, $url);
			list($r, $mess) = gsbCheckURL(&$args, $url, &$numberOfChecks, &$failures, &$prefix, &$checkedURLs, &$checkedDomains, &$howToGetThere);
			if ($r != "OK") {
				$result = $r;
			}
			$message .= "$mess";
			
			// Go deeper
			list($r, $mess) = gsbExploreLinks(&$args, $url, 0, $maxDepth, &$numberOfChecks, &$failures, &$prefix, &$checkedURLs, &$checkedDomains, &$howToGetThere);
			
			if ($r != "OK") {
				$result = $r;
			}
			$message .= "$mess";
		}
		catch (Exception $x) {
			echoIfVerbose("In gsbDailyScan Exception: " . $x->getMessage() . " $url\n");  // use $url not $das
			writeLogAlert("In gsbDailyScan Exception: " . $x->getMessage() . " $url\n");  // use $url not $das
		}
	
		if ($result == "OK") {
			$message .= "GSB reports all is OK\n";
		}
		$message .= INDENT . "$numberOfChecks GSB inquiries were made.\n";
		if ($failures > 0) {
			$message .= INDENT . "$failures GSB connections failed.\n";
		}
		if ($args['time'] <= 60) { 
			// If next run is less than an hour from now, then flag so we don't possibly send twice this hour
			$privateStore[$filterName][$url]['gsbdailyranalready'] = true;
		}
	}
	else {
		// Clear the 'flag' that says we've sent today's notification. So it can be sent tomorrow.
		$privateStore[$filterName][$url]['gsbdailyranalready'] = false;
	}
	
	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function gsbdailyInit($content, $args, $privateStore) {
	$filterName = "v";
	$result   = "OK";						// default result
	$url = $args['url'];
	$contentLength = strlen($content);
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Initialized. URL is " . $args['url'];

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function gsbdailyDestroy($content, $args, $privateStore) {
	$filterName = "gsbdaily";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function gsbDaily($args) {
	$filterName = "gsbdaily";
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


///////////////////////////////// 
///////////////////////////////// 

?>