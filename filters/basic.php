<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: basic
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

function basicScan($content, $args, $privateStore) {
	$REFUSAL_TIME = 5;						// connections failing FASTER than this are "refusals"
											// longer than this number will be "timeouts" - in seconds
	$filterName = 'basic';
	$result   = "OK";						// default result
	$url = $args['url'];
	$message = '';
	$contentLength = strlen($content);
	$elapsedTime = roundTime($args['elapsedtime']);	// rounding precision is defined in cyberspark.sysdefs.php
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
		// CLOUDFLARE 520 thru 524
		if ($httpResult['code'] >= 520 && $httpResult['code'] <= 524) {
			$result = "HTTP";
			$message = "If the site uses Cloudflare, and if Cloudflare thinks the underlying host is unresponsive, it returns a error number between 520 and 524. ";
			$message .= "If the site is not using Cloudflare, then you still should look into this error. ";
			$message .= "The HTTP error number is ".$httpResult['code'].". ";
			try {
				// ob_start();
				// print_r($httpResult['headers']);
				// $message .= ob_get_clean();
				if (isset($httpResult['headers']['Server'])) {
					$sh = $httpResult['headers']['Server'];
					$message .= "Here's a clue... The reported server type is '$sh'. ";
				}
			}
			catch (Exception $allEX) {
			}
		}
		// CLOUDFLARE 410
		if ($httpResult['code'] == 410) {
			$result = "HTTP";
			$message = "If the site uses Cloudflare, and if Cloudflare thinks the underlying host is 'gone' it returns error 410. ";
			$message .= "If the site is not using Cloudflare, then you still should look into this error. ";
			$message .= "The HTTP error number is ".$httpResult['code'].". ";
			try {
				// ob_start();
				// print_r($httpResult['headers']);
				// $message .= ob_get_clean();
				if (isset($httpResult['headers']['Server'])) {
					$sh = $httpResult['headers']['Server'];
					$message .= "Here's a clue... The reported server type is '$sh'. ";
				}
			}
			catch (Exception $allEX) {
			}
		}
	}
	return array($message, $result, $privateStore);
	
}

function basicInit($content, $args, $privateStore) {
	$filterName = 'basic';
	// $content is the CONTENT returned from the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	//    The actual URL is in $args['url']
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Initialized. URL is " . $args['url'];
	$result   = "OK";

	return array($message, $result, $privateStore);
	
}

function basicDestroy($content, $args, $privateStore) {
	$filterName = 'basic';
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

function basic($args) {
	$filterName = 'basic';
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