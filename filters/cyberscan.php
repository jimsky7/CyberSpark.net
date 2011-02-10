<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: cyberscan
		Scans the results of a csscan.php at the target URL.
	*/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";
include_once "include/functions.inc";

define('CYBERSCAN_MAX_ALERTS', 2);

///////////////////////////////// 
function cyberscanScan($content, $args, $privateStore) {
	$filterName = "cyberscan";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

	$contentLength = strlen($content);

	///////////////////////////////// 
	// General strategy:
	//   This filter is designed to look at the result of a cyberspark-scan run on
	//   a target computer.  The URL will look something like this:
	//     http://blog.red7.com/cyberspark-scan.php?report=3
	//   and the results contain certain critical values that we want to look at.
	//   If any of those change, then we send an appropriate alert.
	
	echoIfVerbose("The [Cyberscan] filter was activated \n");
	$newURL = !isset($privateStore[$filterName][$url]['length']) || (strlen($privateStore[$filterName][$url]['length']) == 0);
	
	$privateStore[$filterName][$url]['length'] = $contentLength;	// save, but we don't care if it changed
	
	// Find critical values on the results page
	$phrases = array(
		"PHP files examined"=>'filesexamined',
		"PHP files with suspicious"=>'filessuspicious',
		"New files"=>'newfiles',
		"Changed size"=>'changedsize',
		"Files gone"=>'filesgone'
	);
	$first = true;
	foreach ($phrases as $phrase=>$key) {
		try {
			$i = stripos($content, $phrase);
			if ($i !== false) {
				$iEOL = stripos($content, "\n", $i);	// assume Unix or Windows line breaks
				if ($iEOL !== false) {
					$s = flatten(substr($content, $i, $iEOL-$i));
					if (!$newURL) {
						// Previously-seen URL
						if (strcmp($s, $privateStore[$filterName][$url][$key]) != 0) {
							// Changed
							$result = "Changed";
							$message .= ($first?"\n":"").INDENT."Current state (" . $s . ") Previous state (" . $privateStore[$filterName][$url][$key] . ")\n";
							echoIfVerbose(($first?"\n":"")."NEW $s WAS " . $privateStore[$filterName][$url][$key] . " \n");
							$first = false;
						}
					}
					else {
					}
					// Old or new URL regardless, save new value
					$privateStore[$filterName][$url][$key] = $s;
				}
			}
		}
		catch (Exception $x) {
			echoIfVerbose("Exception: " . $x->getMessage() . "\n");
		}
	}

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function cyberscanInit($content, $args, $privateStore) {
	$filterName = "cyberscan";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$result   = "OK";						// default result
	$message = "[filterName] Scanning " . $args['url'];

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function cyberscanDestroy($content, $args, $privateStore) {
	$filterName = "cyberscan";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$result   = "OK";						// default result
	$message = "[$filterName] Shut down.";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function cyberscan($args) {
	$filterName = "cyberscan";
 	if (!registerFilterHook($filterName, 'scan', 'cyberscanScan', 40)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', 'cyberscanInit', 40)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', 'cyberscanDestroy', 40)) {
		echo "The filter '$filterName' was unable to add a 'Destroy' hook. \n";	
		return false;
	}
	return true;
}

?>