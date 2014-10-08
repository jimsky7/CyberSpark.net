<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: headers
		Records the length of HTTP headers Alerts if the
		length changes since the last time it was observed.
		Keeps a list of the last few lengths it sees. 
		This way if headers flip-flop by a few bytes periodically
		they do not get repeatedly flagged.

		NOTE: This filter's action is derived from 'checklength'
	**/
	
	/**
	    See the file 'how_to_make_a_plugin_filter.php' for instructions
	    on how to make one of these and integrate it into the CyberSpark daemons.
	**/

	/** 
		$args['httpresult']['headers'] must contain the headers for the page 
	**/

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

	// Headers to ignore.
	// Note: Don't put anything beginning "X-" in the array,
	//   because it will be removed separately anyway.
	// Note: These are case-sensitive because of the way the code is written, sorry.
	$headersToIgnore = array(
		'Date',
		'Expires',
		'Set-Cookie',
		'Via',
		'Age',
		'Last-Modified',
		'Set-Cookie',
		'Content-Length',
		'Etag',
		'Transfer-Encoding',					// Sad, but can't use reliably
//	misc cache
		'Cache-Control',
//	Varnish
//	WIX.com
		'ETag'
	);

	$filterName = 'headers';
	$result   = "OK";						// default result
	$url = $args['url'];
	$headers = $args['httpresult']['headers'];
	$hs = '';
	$message = "\n";
	$lengthsLimit = 3;						// how many length revisions to retain
		
	// Remove headers we do not intend to check or retain
	// The "Ignoring" message will only be seen by recipient of notification 
	//   if some of the other headers have changed, which triggers notification.
	try {
		$ignored = false;
		foreach ($headersToIgnore as $headerName) {
			if (isset($headers[$headerName])) {
				if (!$ignored) {
					$message .= INDENT . "Ignoring the following headers: \n";
					$ignored = true;
				}
				$message .= INDENT . INDENT . "$headerName: $headers[$headerName]\n";
				unset($headers[$headerName]);
			}
		}
		// Remove any header that begins "X-"
		foreach ($headers as $headerName=>$value) {
			if (strncasecmp($headerName, "X-", 2) == 0) {
				if (!$ignored) {
					$message .= INDENT . "Ignoring the following headers: \n";
					$ignored = true;
				}
				$message .= INDENT . INDENT . "$headerName: $headers[$headerName]\n";
				unset($headers[$headerName]);
			}
		}
	}
	catch (Exception $zvuso) {
		$message .= INDENT . "Exception ". $zvuso->getMessage()." \n";
	}
	
	// Concatenate (remaining) headers into a single string
	foreach ($headers as $hk=>$hv) {
		$hs .= $hk . $hv;
	}
	$headersLength = strlen($hs);
	// $hs is the concatenated headers (string) being checked right now
	// $content is the page content (which is irrelevant for this filter)
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	// See whether length has changed since last time
	if (isset($privateStore[$filterName][$url]['header_lengths'])) {
		// This URL has been seen before
		$lengthsString = $privateStore[$filterName][$url]['header_lengths'];
		if (($lengthsString != null) && (strlen($lengthsString) > 0)) {
			$lengths = explode(",", $lengthsString);
			// Retain only the most recent length values (ones on the end)
			$i = count($lengths);
			if ($i > $lengthsLimit) {
				// Trim initial values, leaving trailing (more recent) values
				for ($j=0; $j<($i-$lengthsLimit); $j++) {
					unset($lengths[$j]);
				}
				// Build a new lengths string from the remaining values
				$lengthsString = '';
				foreach ($lengths as $oneLength) {
					$lengthsString .= "$oneLength,";				
				}
				// Trim trailing ','
				$lengthsString = trim($lengthsString, ',');
				// Make a new lengths array
				$lengths = explode(",", $lengthsString);
			}
		}
		else {
			$lengthsString = '';
			$lengths = array('');
		}
		$lengthMatched = false;
		foreach ($lengths as $oneLength) {
			if ($headersLength == (int)$oneLength) {
				// Current length matches one of the previous lengths
				$lengthMatched = true;
				break;
			}
		}
		if (!$lengthMatched) {
			// Changed
			$message .= INDENT ."HTTP headers changed. New length is " . $headersLength . "\n";
			$message .= INDENT .INDENT . "Recent lengths for comparison [" . $lengthsString . "]\n";
			// Show new headers
			$message .= INDENT . "Headers remaining for analysis:\n";
			foreach ($headers as $hk=>$hv) {
				$message .= INDENT . INDENT ."$hk: $hv\n";
			}
			// Show previous headers
			if (isset($privateStore[$filterName][$url]['header_contents'])) {
				$message .= INDENT . "Headers from the previous analysis:\n";
				$hc = unserialize($privateStore[$filterName][$url]['header_contents']);
				foreach ($hc as $hk=>$hv) {
					$message .= INDENT . INDENT ."$hk: $hv\n";
				}
			}
			$result = "Warning";
			// $lengthsString contains (comma separated) all of the lengths of this URL
			// that have recently been seen by this filter. Note that it persists even if
			// our parent daemon is shut down and restarted.
			$lengthsString .= "," . (string)$headersLength;
		}
		else {
			// No change
		}
	}
	else {
		// This URL has not been seen before
		$lengthsString = (string)$headersLength;
	}
	// Record the length of the headers for this URL
	$privateStore[$filterName][$url]['header_lengths'] = $lengthsString;
	// Save the headers in case we need to report out when they change
	// Note that some actual headers may have been removed earlier, so they're
	//   not recorded here, and they aren't checked next time around either.
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