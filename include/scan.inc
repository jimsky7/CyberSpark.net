<?php
	/**
		CyberSpark.net monitoring-alerting system
		perform one round of scanning (all URLs once)
	*/

include_once "include/classdefs.inc";
include_once "cyberspark.sysdefs.php";

///////////////////////////////////////////////////////////////////////////////////
function scan($properties, $filters, &$store) {
	$scanResults = array();
	
	$urlCount = count($properties['urls']);		// number of URLs to examine
	$urlNumber = 0;								// index into $urls array

	$urls = $properties['urls'];		// array of URLs to look at
	while ($urlNumber < $urlCount) {
		$url 				= $urls[$urlNumber]->url;
		$conditions		= $urls[$urlNumber]->conditions;
		$emails 			= $urls[$urlNumber]->emails;
		
		// Note that a very few servers cannot handle CAPS in FQDNs, so purify them now.
		// (This only lowercases the FQDN and not the rest of the URL; otherwise we'd have
		//   trouble with case-sensitive filesystems, which is trouble we do not seek.)
		$url = lowercaseFQDN($url);

		echoIfVerbose("Scanning   $url \n");
		echoIfVerbose("Conditions $conditions \n");
		echoIfVerbose("Emails     $emails \n");

		// Sometimes this process becomes unresponsive, or just plain fails and we'd like
		//   to know what URL it was working on when it failed. This value is included in
		//   the message that cyberspark.php sends by email.
		if (isset($properties['urlfilename'])) {
			@file_put_contents($properties['urlfilename'], $url);
		}
		
		$before = time();
		
		// Do an HTTP GET on this URL. It begins "http://" or "https://"
		// (Note that we do not send any explicit AUTH information and SSL cert will not be checked.)
		if (isset($properties['maxredirects'])) {
			$httpResult = httpGet($url, $properties['useragent'], $properties['timeout'], null, array('maxredirects'=>$properties['maxredirects']));
		}
		else {
			$httpResult = httpGet($url, $properties['useragent'], $properties['timeout'], null, null);
		}
		
		// Calculate elapsed time
		// Note that httpGet() also returns cURL info, which has much more detail,
		//   such as latency, DNS lookup time, transfer time, if you wish to use.
		//   It's in $httpResult['curl_info'] so take a look at documentation at PHP.NET
//		$elapsedTime = time() - $before;							// type 'int'    (before 2014-10-14)
		$elapsedTime = $httpResult['curl_info']['total_time'];		// type 'double' (as of  2014-10-14)
		
		echoIfVerbose("HTTP result code " . $httpResult['code'] . "\n");
		$httpError = '';
		if (isset($httpResult['error'])) {
			$httpError = $httpResult['error'];
		}
		echoIfVerbose("HTTP error       $httpError \n");
		echoIfVerbose('HTTP length      ' . strlen($httpResult['body']) . "\n");
		echoIfVerbose("Elapsed time     $elapsedTime \n\n");
		
		$scanResults[$urlNumber] = "";
		/////////////////////////////////////////////////////////
		
		// First check to see if this is a "reverse" scan - in other words, we are to send
		//   a report only if a URL becomes present, and not if it's absent
		$ifExists	= (stripos($conditions, "exist") !== false) || (stripos($conditions, "200") !== false);
		if ($ifExists) {
			if (isset($httpResult['code']) && ($httpResult['code']==200)) {
				// Properties asked for us to report when a URL appears
				//   (and not when it's absent)
				$scanResults[$urlNumber] = "Alert     " . "$url ... has appeared!  \n";
				writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
			}
			else {
				// This file isn't there - that's good
				$scanResults[$urlNumber] = "OK        " . "Doesn't exist (that's good!): $url \n";
				writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
			}
			$urlNumber++;
			continue;
		}
		
		
		// Scan with regular filters	
		if (!isset($httpResult['code']) || ($httpResult['code'] != 200)) {
			/////////////////////////////////////////////////////////
			// HTTP failed - some error code other than "200"
			// Still need to execute the 'basic' filter even in this case
			$rankIndex = 0;
			$top		= count($filters);
			$message = "";
			$isOK = false;
			while ($rankIndex < $top) {
				$filterName = $filters[$rankIndex]->name;
				if (($filterName == "basic") && isset($filters[$rankIndex]->scan)) {
					$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
					try {
						// Run 'basic' filter
						list($mess, $result, $st) = call_user_func($filters[$rankIndex]->scan, $httpResult['body'], $filterArgs, $store[$filters[$rankIndex]->name]);
						if (isset($st)) {
							// Save this filter's private store
							$store[$filters[$rankIndex]->name] = $st;
						}
					}
					catch (Exception $fx) {
						// The filter barfed
						$mess = "  Exception: " . $fx->getMessage() . "\n";
						echoIfVerbose("[$filterName] $mess");
						writeLogAlert("[$filterName] $mess");
						$result = "Exception";
						$isOK = false;
					}
					if (isset($mess) && isset($result)) {
						// Add any message returned by the filter
						$message .= "  " . $mess . "\n";
						// If filter said anything other than "OK" then there's
						//   an overall failure for this URL.
						$isOK = $isOK && ($result == "OK");		// (both terms must be true)
					}
				}
				$rankIndex++;
			}
			$prefix = "Failed    ";
			if ($result != "OK") {
				$prefix = sprintf("%-' 10s", $result);	// pad string out to 10 chars
			}
			$httpCode = "";
			if (isset($httpResult['code'])) {
				$code = $httpResult['code'];
				$httpCode = "Error $code";
				// Check and process cURL responses
				if ($httpResult['code'] < 200) {
					$httpCode = "";
					if (isset($httpResult['error']) && (strlen($httpResult['error']) > 0)) {
						$message .= "          php5-curl says [$code] '$httpResult[error]'";
					}
				}
				// Make a special note for HTTP 300 through 399
				if (($code >= 300) && ($code <= 399)) {
					// Convert "Location" and "location" so we detect even if lowercase
					//   some malware doesn't capitalize. (2014-10-14 sky)
					$location = null;
					if (isset($httpResult['headers']['Location'])) {
						$location = $httpResult['headers']['Location'];
					}
					if (isset($httpResult['headers']['location'])) {
						$location = $httpResult['headers']['location'];
					}
					if ($location != null) {
						// Note: "Location" requires initial cap and must match what the server returns, so if
						//   the server doesn't cap it, then this comparison didn't work.
						$message .= "          (The redirect to '$location' was not followed. DO NOT CLICK!)";
					}
				}
			}
	
	
	
			$scanResults[$urlNumber] = $prefix . $httpCode . " $url  $message \n";
			writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
		}
		else {
			/////////////////////////////////////////////////////////
			// HTTP succeeded (code==200)
			// SCAN ONE URL HERE USING ALL DEFINED FILTERS
			$rankIndex = 0;
			$top = count($filters);
			$message = "";
			$isOK = true;
						
			
			// Run all filters (including 'basic')
			while ($rankIndex < $top) {

				// There are filters in different flavors ('scan' 'init' 'destroy') -- only use 'scan' !
				// They've already been ranked in the order they should be applied (and thus displayed or emailed)
				if (isset($filters[$rankIndex]->scan)) {
					$filterName = $filters[$rankIndex]->name;
					if (($filterName == "basic") || ($filterName == "find") || isACondition($urls[$urlNumber], $filterName)) {
						// Filter either was asked for in the properties for this URL,
						// OR its name is 'basic' OR its name is 'find'
						echoIfVerbose("Applying filter '$filterName' to URL $url \n");		
						
						$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
						
						try {
							list($mess, $result, $st) = call_user_func($filters[$rankIndex]->scan, $httpResult['body'], $filterArgs, $store[$filters[$rankIndex]->name]);
							if (isset($st)) {
								// Save this filter's private store
								$store[$filters[$rankIndex]->name] = $st;
							}
						}
						catch (Exception $fx) {
							// The filter barfed
							$mess = "  Exception: " . $fx->getMessage() . "\n";
							echoIfVerbose("[$filterName] $mess");
							writeLogAlert("[$filterName] $mess");
							$result = "Exception";
							$isOK = false;
						}
						if (isset($mess) && isset($result)) {
							$prefix = sprintf("%-' 10s", $result);	// pad string out to 10 chars
							// Add any message returned by the filter
							$message .= $prefix . "[$filterName] $mess \n";
							// If filter said anything other than "OK" then there's
							//   an overall failure for this URL.
							$isOK = $isOK && ($result == "OK");		// (both terms must be true)
						}
					}
				}
				$rankIndex++;
			}
			$scanResults[$urlNumber] = ($isOK?'OK        ':'Errors    ') . "$url\n$message\n";
			writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
		}
		usleep(100);	// Yield a minimal time. Just polite in case OS needs to do anything else.
						// Sometimes HTTP is very slow on a URL and although it's supposed to be
						// non-blocking, I'm not 100% convinced it is.  [SKY: 2012-12-26]
		$urlNumber++;
	}

	echoIfVerbose("End scanning\n");

	return $scanResults;

}

///////////////////////////////////////////////////////////////////////////////////
function setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime) {
	$filterArgs = array();
	$filterArgs['url']			= $url;
	$filterArgs['conditions']	= $conditions;
	$filterArgs['httpresult']	= $httpResult;
	$filterArgs['elapsedtime']	= $elapsedTime;
	// Transfer all properties from the 'properties' file
	//   This way if you want to build your own 'filter' you can also add parameters
	//   as values in the properties files, with names you dream up, as long as they
	//   don't duplicate a name used elsewhere. You could pre-pend the filter name, if you
	//   wish, to keep them unique. The param name was forced lowercase when parsed.
	//   Such as:
	//     FILTERNAME_PROPERTY=abcdefg
	foreach ($properties as $key => $value) {
		$filterArgs[$key] = $properties[$key];
	}
// It used to be done this way...
//	// (In the future, consider copying all of them - why eliminate any?)
//	$filterArgs['timeout'] 		= $properties['timeout'];
//	$filterArgs['slow']    		= $properties['slow'];
//	$filterArgs['verbose']    	= $properties['verbose'];
//	$filterArgs['gsbserver']	= $properties['gsbserver'];
//	$filterArgs['notify']		= $properties['notify'];
//	$filterArgs['time']			= $properties['time'];
//	$filterArgs['load']			= $properties['load'];
//	$filterArgs['disk']			= $properties['disk'];
//	$filterArgs['useragent']    = $properties['useragent'];	// because some filter do further GET or POST
	return $filterArgs;
}

?>