<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: headers
		Records the length of a page. Alerts if the
		length changes since the last time it was observed.
		Keeps a list of the lengths it sees and only reports
		each one the first time it is seen.  This way pages
		that vary by a few bytes and then return to usual size
		do not get repeatedly flagged.

		NOTE: THIS IS EXACTLY THE SAME CODE AS 'checklength' - DUPLICATE FILE with different filter name
	**/
	
	/**
	    See the file 'how_to_make_a_plugin_filter.php' for instructions
	    on how to make one of these and integrate it into the CyberSpark daemons.
	**/

/** $args['httpresult']['headers'] should contain the headers for the page **/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "include/echolog.inc";

//// ...Scan()
//// This function is called when a URL is being scanned and when 'length' has been
//// specified as a filter for the URL (on the line in the properties file).
//// $content contains the result of the HTTP GET on the URL.
//// $args holds arguments/parameters/properties from the daemon
//// $privateStore is an associative array of data which contains, if indexed properly,
////   persistent data related to the URL being scanned.
function headersScan($content, $args, $privateStore) {
	$filterName = 'headers';
	$result   = "OK";						// default result
	$url = $args['url'];
	$headers = $args['httpresult']['headers'];
	$hs = '';
	foreach ($headers as $hk=>$hv) {
		$hs .= $hk . $hv;
	}
	$contentLength = strlen($hs);
	$message = "";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	// See whether length has changed since last time
	if (isset($privateStore[$filterName][$url]['header_lengths'])) {
		// This URL has been seen before
		$lengthsString = $privateStore[$filterName][$url]['header_lengths'];
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
			$message .= "HTTP headers changed. New length is " . $contentLength . "\n";
			$message .= INDENT . "Previous lengths include [" . $lengthsString . "]\n";
			// Show new headers
			$message .= INDENT . "Headers are:\n";
			foreach ($headers as $hk=>$hv) {
				$message .= INDENT . INDENT ."$hk: $hv\n";
			}
			// Show previous headers
			if (isset($privateStore[$filterName][$url]['header_contents'])) {
				$message .= INDENT . "Headers had been:\n";
				$hc = unserialize($privateStore[$filterName][$url]['header_contents']);
				foreach ($hc as $hk=>$hv) {
					$message .= INDENT . INDENT ."$hk: $hv\n";
				}
			}
			$result = "Warning";
			// $lengthsString contains (comma separated) all of the lengths of this URL
			// that have ever been seen by this filter. Note that it persists even if
			// our parent daemon is shut down and restarted.
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
	$privateStore[$filterName][$url]['header_lengths'] = $lengthsString;
	// Save the headers in case we need to report out when they change
	$privateStore[$filterName][$url]['header_contents'] = serialize($headers);
	return array($message, $result, $privateStore);
}

//// ...Init()
//// This function is called by the daemon once when it is first loaded.
//// It returns a message, but doesn't touch the private date (in $privateStore).
function headersInit($content, $args, $privateStore) {
	$filterName = 'headers';
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Initialized. URL is " . $args['url'];
	$result   = "OK";
//echo "filter 'init' 'length'\n";	
	return array($message, $result, $privateStore);
	
}

//// ...Destroy()
//// This function is called as the daemon is shutting down. We get an instance of
//// the daemon's "private" database in $privateStore, which we simply pass back. We also
//// pass back a message saying that this filter has properly shut down.
function headersDestroy($content, $args, $privateStore) {
	$filterName = 'headers';
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

//// FILTER_NAME()
//// This plugin filter checks the length of the HTTP headers and notifies if they have changed.
//// This is a strict length check with no additional analysis.
//// Data is kept in the database that is presented during execution as $privateStore.
//// (Note that the name of this function matches the filename plus ".php" -- this is required.)
function headers($args) {
	$filterName = 'headers';
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