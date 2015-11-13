<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: headers
		Analyzes HTTP headers.  Alerts if new headers or values appear.
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
include_once "include/echolog.php";
include_once "include/filter_functions.php";


//// ...Scan()
//// This function is called when a URL is being scanned and when 'headers' has been
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
		'Via',
		'Age',
		'Last-Modified',						
		'Set-Cookie',
		'Content-Length',
		'Etag',
//		'Transfer-Encoding',				// Sad, but can't use reliably
//		'Keep-Alive',						// Varies too much. Not so useful
//		'Connection',						// 'keep-alive' and so forth
//	misc cache
		'Cache-Control',
		'Expires',
		'Fastcgi-Cache',
//	Varnish
//  Cloudflare
		'CF-RAY',
//	Yahoo (hk particularly)
		'Y-Trace',
//	WIX.com
		'ETag'
	);

	$filterName = 'headers';
	$result   = "OK";						// default result
	$url = $args['url'];
	$headers = $args['httpresult']['headers'];
//	$hs = '';
	$message = "\n";
	$ignored = false;
	$ignoredHeaders = '';
		
	// Remove headers we do not intend to check or retain
	// The "Ignoring" message will only be seen by recipient of notification 
	//   if some of the other headers have changed, which triggers notification.
	try {
		foreach ($headersToIgnore as $headerName) {
			if (isset($headers[$headerName])) {
				if (!$ignored) {
					$ignoredHeaders .= INDENT . "Ignoring the following headers: \n";
					$ignored = true;
				}
				$ignoredHeaders .= INDENT . INDENT . "$headerName: $headers[$headerName]\n";
				unset($headers[$headerName]);
			}
		}
		// Remove any header that begins "X-"
		foreach ($headers as $headerName=>$value) {
			if (strncasecmp($headerName, "X-", 2) == 0) {
				if (!$ignored) {
					$ignoredHeaders .= INDENT . "Ignoring the following headers: \n";
					$ignored = true;
				}
				$ignoredHeaders .= INDENT . INDENT . "$headerName: $headers[$headerName]\n";
				unset($headers[$headerName]);
			}
		}
	}
	catch (Exception $hrmvx) {
		$message .= INDENT . "Exception while removing headers ". $hrmvx->getMessage()." \n";
	}
		
	// Unset data from previous version of this filter
	unset($privateStore[$filterName][$url]['header_contents']);
	unset($privateStore[$filterName][$url]['header_lengths']);
	unset($privateStore[$filterName][$url]['header_details']);

	$message .= INDENT ."Analyzing HTTP headers.\n";

	// Insert ignored headers message
	$analysis = '';
	$analysis .= $ignoredHeaders;
	if (count($headers)) {
		// Analysis
		$analysis .= INDENT . "Analysis of remaining headers: \n";
		foreach ($headers as $hk=>$hv) {
			// Add to message
			$analysis .= INDENT . INDENT ."$hk: $hv\n";

			// Has this value appeared before?
			$pva = null;
			if (isset($privateStore[$filterName][$url]['header_values'][$hk])) {
				$pva = $privateStore[$filterName][$url]['header_values'][$hk];
			}
			$isNew = true;
			if ($pva != null) {
				foreach ($pva as $pk=>$pv) {
					if (strcasecmp($pv, $hv) == 0) {
						// This value of this header has been seen before
						$analysis .= INDENT . INDENT . INDENT ."(Seen before)\n";
						$isNew = false;
						break;
					}
				}
			} 
			if ($isNew) {
				// This value of this header is new
				$result = "Warning";
				$privateStore[$filterName][$url]['header_values'][$hk][] = $hv;
				$analysis .= INDENT . INDENT . INDENT ."(A new value)";
				if ($pva != null) {
					$analysis .= " Previous values seen:\n";
					foreach ($pva as $pk=>$pv) {
						$analysis .= INDENT . INDENT . INDENT ."$pv\n";
					}
				} 
				else {
					$analysis .= "\n";
				}
			}
			else {
				//	Turn this statement on if you wish to see header analysis every time.
				//	You might consider this for debugging, but not for production.
				//	$result = "Advice ";
			}
		}
		if ($result != 'OK') {
			$message .= $analysis;
		}
	}
	else {
		// No change
		$message .= INDENT . "No headers remain for analysis.\n";
	}



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