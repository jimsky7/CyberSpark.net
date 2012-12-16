<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: cyberscan
		Scans the results of a csscan.php at the target URL.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";
include_once "include/functions.inc";

define('CYBERSCAN_MAX_ALERTS', 2);
define('DISKWARNING', 80);
define('DISKCRITICAL', 90);
define('LOADWARNING', 2);		// integer only
define('LOADCRITICAL', 4);		// integer only

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
		"Files gone"=>'filesgone',
		"Disk:"=>'disk',
		"Load:"=>'load',
		"Alert:"=>'alert'
	);
	$first = true;
	foreach ($phrases as $phrase=>$key) {
		// Looking for one of the key phrases
		try {
			$i = stripos($content, $phrase);
			if ($i !== false) {
				// Found one key phrase
				$iEOL = stripos($content, "\n", $i);	// assume Unix or Windows line breaks
				if ($iEOL !== false) {
					$s = flatten(substr($content, $i, $iEOL-$i));
					// Certain results always cause a notification
					if ($key == 'disk') {
						// Detect disk almost full
						$s = condenseBlanks($s);
						list($beginning, $other) = explode('%', $s, 2);
						list($beginning, $pct) = explode(' ', $beginning, 2);
//						// Only notify on change
//						if (isset($privateStore[$filterName][$url][$key]) && strcmp($s, $privateStore[$filterName][$url][$key]) != 0) {
							$diskWarningLevel = DISKWARNING;
							$diskCriticalLevel = DISKCRITICAL;
							if (isset($args['disk']) && ($args['disk'] > 0) && ($args['disk'] <= 100)) {
								$diskWarningLevel = $args['disk'];
								$diskCriticalLevel = (int)(100-((100-$diskWarningLevel)/2));
							}
							if ($pct > $diskWarningLevel) {
								$result = 'Warning';
								if ($pct > $diskCriticalLevel) {
									$result = 'Critical';
								}
								$message .= ($first?"\n":"").INDENT."Current state '$result' - running out of space (" . $s . ") Previous state (" . $privateStore[$filterName][$url][$key] . ")\n";
								echoIfVerbose(($first?"\n":"")."NEW $s WAS " . $privateStore[$filterName][$url][$key] . " \n");
								$first = false;
							}
//						}
					}
					else if ($key == 'load') {
						// Detect high LOADAVG
						//   The value to consider high comes in to the filter as $args['load']
						//   (if not, then a default is used). This can be specified for an 
						//   individual sniffer in the properties file as
						//     LOAD=n
						//   where 'n' is the LOADAVG number above which you want to notify.
						$s = condenseBlanks($s);
						list($beginning, $currentLoad, $previous, $longago) = explode(' ', $s, 4);
						list($currentLoad, $frax) = explode('.', $currentLoad, 2);
//						// Only notify on change
//						if (isset($privateStore[$filterName][$url][$key]) && strcmp($s, $privateStore[$filterName][$url][$key]) != 0) {
							$loadWarningLevel = LOADWARNING;
							$loadCriticalLevel = LOADCRITICAL;
							if (isset($args['load']) && ($args['load'] > 0)) {
								$loadWarningLevel = $args['load'];
								$loadCriticalLevel = $loadWarningLevel * 2;
							}
							if ($currentLoad >= $loadWarningLevel) {
								$result = 'Warning';
								if ($currentLoad >= $loadCriticalLevel) {
									$result = 'Critical';
								}
								$message .= ($first?"\n":"").INDENT."Current state '$result' - load is high (" . $s . ") Previous state (" . $privateStore[$filterName][$url][$key] . ")\n";
								echoIfVerbose(($first?"\n":"")."NEW $s WAS " . $privateStore[$filterName][$url][$key] . " \n");
								$first = false;
							}
//						}
					}
					else if ($key == 'alert') {
						$s = condenseBlanks($s);
						$result = "Critical";
						$message .= ($first?"\n":"").INDENT."Alert: ($s)\n";
						$first = false;
					}
					else {
						// Most results are just watched for changes
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