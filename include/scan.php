<?php
	/**
		CyberSpark.net monitoring-alerting system
		perform one round of scanning (all URLs once)
	*/

global $path;

include_once $path."include/classdefs.php";
include_once $path."cyberspark.sysdefs.php";

///////////////////////////////////////////////////////////////////////////////////
function scan($properties, $filters, &$store) {
	global $BASIC_FILTERS;
	
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
		$elapsedTime = time() - $before;									// type 'int'    (before 2014-10-14)
		if (isset($httpResult['curl_info']['total_time'])) {
			$elapsedTime = $httpResult['curl_info']['total_time'];		// type 'double' (as of  2014-10-14)
		}
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
				
		if (!isset($httpResult['code']) || ($httpResult['code'] != 200)) {
			/////////////////////////////////////////////////////////
			// HTTP failed - some error code other than "200"
			// Still need to execute some filters even in this case
			$message = "";
			$isOK = false;
			$content = null;
			if (isset($httpResult['body'])) {
				$content = $httpResult['body'];
			}
			$result = "Failed";
						
			$httpCode = "";
			if (isset($httpResult['code'])) {
				$code = $httpResult['code'];
				$httpCode = "Error $code";
				// Check and process cURL responses
				if ($httpResult['code'] < 200) {
					// Under 200 is a cURL response
					$httpCode = "";
					if (isset($httpResult['error']) && (strlen($httpResult['error']) > 0)) {
						$message .= "          php5-curl says [$code] '$httpResult[error]'\n";
						// If available, add ASN information
						// This only happens if the URL was previously successfully retrieved
						//   and ASN information was saved.
						if (isset($store['asn'])) {
							$privateStore = $store['asn'];
							if (isset($privateStore[$url]['ip'])) {
								$message .= "          IP: ".$privateStore[$url]['ip']."\n";
							}
							if (isset($privateStore[$url]['asn'])) {
								$message .= "          ASN: ".$privateStore[$url]['asn']."\n";
							}
							if (isset($privateStore[$url]['asn'])) {
								$message .= "          Operator: ".$privateStore[$url]['operator']."\n";
							}
						}
						else {
							$message .= "          No ASN information is available.\n";
						}
						// If available, add geolocation information
						// This only happens if the URL was previously successfully retrieved
						//   and geolocation information was saved.
						if (isset($store['geo'])) {
							$privateStore = $store['geo'];
							if (isset($privateStore[$url]['ip'])) {
								$message .= "          IP: ".$privateStore[$url]['ip']."\n";
							}
							if (isset($privateStore[$url]['city'])) {
								$message .= "          City: ".$privateStore[$url]['city']."\n";
							}
							if (isset($privateStore[$url]['country_name'])) {
								$message .= "          Country: ".$privateStore[$url]['country_name']."\n";	
							}
						}
						else {
							$message .= "          No GEO information is available.\n";
						}
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
						$message .= "          (The redirect to '$location' was not followed. DO NOT CLICK!)\n";
					}
				}
				// 500-series errors are backoffice failures, including CLoudflare errors
				if (($code >= 500) && ($code <= 599)) {
					// If available, add ASN information
					// This only happens if the URL was previously successfully retrieved
					//   and ASN information was saved.
					if (isset($store['asn'])) {
						$privateStore = $store['asn'];
						if (isset($privateStore[$url]['asn'])) {
							$message .= "          ASN: ".$privateStore[$url]['asn']."\n";
						}
						if (isset($privateStore[$url]['asn'])) {
							$message .= "          Operator: ".$privateStore[$url]['operator']."\n";
						}
					}
					else {
						$message .= "          No ASN information is available.\n";
					}
					// If available, add geolocation information
					// This only happens if the URL was previously successfully retrieved
					//   and geolocation information was saved.
					if (isset($store['geo'])) {
						$privateStore = $store['geo'];
						if (isset($privateStore[$url]['city'])) {
							$message .= "          City: ".$privateStore[$url]['city']."\n";
						}
						if (isset($privateStore[$url]['country_name'])) {
							$message .= "          Country: ".$privateStore[$url]['country_name']."\n";	
						}
					}
					else {
						$message .= "          No GEO information is available.\n";
					}
				}
				// For any error
				// Run all filters that have been defined as 'basic'
				// These are to be run even if HTTP or cURL returns an error (i.e. no data retrieved from URL)
				// Note that $result already indicates an error, so do not change it here
				foreach ($filters as $filter) {
					$filterName = $filter->name;
					if ((array_search ($filterName, $BASIC_FILTERS) != FALSE) && isset($filter->scan)) {
						$tempResult = 'OK';
						$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
						try {
							if (isNotifyHour($filterArgs['notify']) && ($filter->notify != null)) {
								// During the 'Notify" hour, and if it exists, 'notify' function is invoked.
								list($mess, $tempResult, $st) = call_user_func($filter->notify, $content, $filterArgs, $store[$filterName]);
							}
							else {
								// During all other hours, 'scan' function is invoked
								list($mess, $tempResult, $st) = call_user_func($filter->scan,   $content, $filterArgs, $store[$filterName]);
							}
							if (isset($st)) {
								// Save this filter's private store
								$store[$filterName] = $st;
							}
						}
						catch (Exception $fx) {
							// The filter barfed
							$mess = "  Exception: " . $fx->getMessage() . "\n";
							echoIfVerbose("[$filterName] $mess");
							writeLogAlert("[$filterName] $mess");
							$result = "Exception";
						}
						if (isset($mess) && isset($tempResult)) {
							$pf = sprintf("%-' 10s", $tempResult);	// pad string out to 10 chars
							// Add any message returned by the filter
							$message .= $pf . "[$filterName] $mess \n";
						}
					}
				}
			}
	
			$prefix = "Failed    ";
			if ($result != "OK") {
				$prefix = sprintf("%-' 10s", $result);	// pad string out to 10 chars
			}
			
			$scanResults[$urlNumber] = $prefix . $httpCode . " $url\n$message \n";
			writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
		}
		else {
			/////////////////////////////////////////////////////////
			// HTTP succeeded (code==200)
			// SCAN ONE URL HERE USING ALL DEFINED FILTERS
			$message = "";
			$isOK = true;
			$content = null;
			$contentLength = 0;
			if (isset($httpResult['body'])) {
				$content = $httpResult['body'];
				$contentLength = strlen($content);
			}
						
			// Run all filters (including 'basic')
			foreach ($filters as $filter) {
				// There are filters in different flavors ('scan' 'notify' 'init' 'destroy') -- only use 'scan' or 'notify' here !
				// They've already been ranked in the order they should be applied (and thus displayed or emailed)
				if (isset($filter->scan)) {
					$filterName = $filter->name;
					if (($filterName == "basic") || ($filterName == "find") || isACondition($urls[$urlNumber], $filterName)) {
						// Filter either was asked for in the properties for this URL,
						// OR its name is 'basic' OR its name is 'find'
						echoIfVerbose("Applying filter '$filterName' to URL $url \n");		
						
						$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
						
						try {
							if (isNotifyHour($filterArgs['notify']) && ($filter->notify != null)) {
								list($mess, $result, $st) = call_user_func($filter->notify, $content, $filterArgs, $store[$filterName]);
							}
							else {
								list($mess, $result, $st) = call_user_func($filter->scan, $content, $filterArgs, $store[$filterName]);
							}
							if (isset($st)) {
								// Save this filter's private store
								$store[$filterName] = $st;
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
			}
			$scanResults[$urlNumber] = ($isOK?'OK        ':'Errors    ') . "$url\n$message\n";
			writeLog($scanResults[$urlNumber], $elapsedTime, $contentLength, "", $conditions, $url, $httpResult['code'], 0);
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