<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: gsb2
		Checks this page against Google Safe Broswing.
		This is the advanced "recursive" code.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.php";

// IMPORTANT NOTE:
// Some functions required for this filter are in gsb.php and
//   so that filter must be present for this one to work.

///////////////////////////////// 
function gsb2Scan($content, $args, $privateStore) {
	$filterName = "gsb2";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

// Thanks for the DOM suggestion! - see
//   http://w-shadow.com/blog/2009/10/20/how-to-extract-html-tags-and-their-attributes-with-php/

	// Remove chars that don't make any difference and can get in the way
	$content = str_replace(array("\r","\n","\t"), "", $content);

	// Get the pertinent HTML tags
	echoIfVerbose("GSB check \n");
	
	$maxDepth	= GSB_DEPTH_2;				// how deep we will spider
	$failures	= 0;						// number of GSB connection failures
	$numberOfChecks = 0;					// number of GSB attempts
	$prefix = "";
	$checkedURLs = array();					// URLs that have been followed already
	$checkedDomains = array();				// domains that have been GSB'd already (including subdirs)
	$howToGetThere= array();			// 'breadcrumbs' for alert message
	
	///////////////////////////////// 
	try {
		// Remove leading underscores. Shouldn't be any, but sometimes there are. Life's a mystery. 2013-05-28 sky
		while (strpos($url, '_') == 0) {
			$link = substr($url, 1);
		}
		// Check the main URL first
//		array_push($howToGetThere, $url);
		// Check one URL
		list($r, $mess) = gsbCheckURL($args, $url, $numberOfChecks, $failures, $prefix, $checkedURLs, $checkedDomains, $howToGetThere);
		if ($r != "OK") {
			$result = $r;
		}
		$message .= "$mess";
		
		// Go deeper
		list($r, $mess) = gsbExploreLinks($args, $url, 0, $maxDepth, $numberOfChecks, $failures, $prefix, $checkedURLs, $checkedDomains, $howToGetThere);
		
		if ($r != "OK") {
			$result = $r;
		}
		$message .= "$mess";
	}
	catch (Exception $x) {
	}

	if ($result == "OK") {
		$message .= "GSB reports all is OK\n";
	}
	$message .= INDENT . "$numberOfChecks GSB inquiries were made.\n";
	if ($failures > 0) {
		$message .= INDENT . "Of those, $failures GSB connections failed.\n";
	}
	echoIfVerbose("$numberOfChecks GSB inquiries were made.\n");
	echoIfVerbose("Of those, $failures GSB connections failed.\n");

	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function gsb2Init($content, $args, $privateStore) {
	$filterName = "gsb2";
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
function gsb2Destroy($content, $args, $privateStore) {
	$filterName = "gsb2";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function gsb2($args) {
	$filterName = "gsb2";
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
// This filter uses various functions from filters/gsb.php

///////////////////////////////// 
///////////////////////////////// 


?>