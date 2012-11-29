<?php
	/**
		CyberSpark.net monitoring-alerting system
		perform one round of scannisetFilterArgsng (all URLs once)
	*/

include_once "include/classdefs.inc";

///////////////////////////////////////////////////////////////////////////////////
function scan($properties, $filters, &$store) {
	$scanResults = array();
	
	$urlCount = count($properties['urls']);		// number of URLs to examine
	$urlNumber = 0;								// index into $urls array

	$urls = $properties['urls'];		// array of URLs to look at
	while ($urlNumber < $urlCount) {
		$url 				= $urls[$urlNumber]->url;
		$conditions			= $urls[$urlNumber]->conditions;
		$emails 			= $urls[$urlNumber]->emails;
		
		// Note that a very few servers cannot handle CAPS in FQDNs, so purify them now.
		// (This only lowercases the FQDN and not the rest of the URL.)
		$url = lowercaseFQDN($url);

		echoIfVerbose("Scanning   " . $url . "\n");
		echoIfVerbose("Conditions " . $conditions . "\n");
		echoIfVerbose("Emails     " . $emails . "\n");

		$before = time();
		
		// Do an HTTP GET on this URL
		$httpResult = httpGet($url, $properties['useragent'], $properties['timeout']);
		$elapsedTime = time() - $before;

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
		
		// First check to see if this is a reverse scan - in other words, we are to send
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
				$httpCode = "Error " . $httpResult['code'];
			}
			$scanResults[$urlNumber] = $prefix . $httpCode . " $url  $message \n";
			writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
		}
		else {
			/////////////////////////////////////////////////////////
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
					if (($filterName == "basic") || isACondition($urls[$urlNumber], $filterName)) {
						// Filter either was asked for in the properties for this URL
						// OR its name is 'basic'
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
			$scanResults[$urlNumber] = ($isOK?"OK        ":"Errors    ") . $url . "\n" . $message . "\n";
			writeLog($scanResults[$urlNumber], $elapsedTime, strlen($httpResult['body']), "", $conditions, $url, $httpResult['code'], 0);
		}
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
	// Transfer certain properties from the 'properties' file
	// (In the future, consider copying all of them - why eliminate any?)
	$filterArgs['timeout'] 		= $properties['timeout'];
	$filterArgs['slow']    		= $properties['slow'];
	$filterArgs['verbose']    	= $properties['verbose'];
	$filterArgs['gsbserver']	= $properties['gsbserver'];
	$filterArgs['notify']		= $properties['notify'];
	$filterArgs['time']			= $properties['time'];
	$filterArgs['load']			= $properties['load'];
	$filterArgs['disk']			= $properties['disk'];
	$filterArgs['useragent']    = $properties['useragent'];	// because some filter do further GET or POST
	return $filterArgs;
}

?>