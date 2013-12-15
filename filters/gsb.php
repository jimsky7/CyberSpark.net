<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: gsb
		Checks this page against Google Safe Browsing.
		In a more advanced mode, checks all links from this page as well.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// NOTE: This filter is required if 'gsbdaily' is to work properly. It includes some
//       of the functions that are defined in this file.

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";

///////////////////////////////// 
function gsbScan($content, $args, $privateStore) {
	$filterName = "gsb";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

	// Remove chars that don't make any difference and can get in the way
	$content = str_replace(array("\r","\n","\t"), "", $content);

	// Get the pertinent HTML tags
	echoIfVerbose("GSB check \n");

	///////////////////////////////// 
	// Find all links in this document
	$dom = new DOMDocument();
	@$dom->loadHTML($content);

	$links = extractLinks("a", "href", $dom, array());
	$links = extractLinks("form", "action", $dom, $links);
	$links = extractLinks("img", "src", $dom, $links);
	$links = extractLinks("link", array("rel","href"), $dom, $links);
	$links = extractLinks("script", "src", $dom, $links);

	$numberOfChecks = 0;
	$prefix = "";
	foreach ($links as $link) {
		$checkLink = str_replace(' ', '+', domainAndSubdirs($link));
		echoIfVerbose("GSB checking $checkLink\n");	
		try {
			$httpResult = httpGet($args['gsbserver'] . "/gsb-check?url=$checkLink", $args['useragent'], 15);
			$numberOfChecks++;
			if (isset($httpResult['code']) && ($httpResult['code'] == 200)) {
				$body = $httpResult['body'];
				echoIfVerbose("$body \n");
				if (strncmp(strtoupper($body), "OK", 2) == 0) {
					// URL is safe
				}
				elseif (strlen($body) > 0) {
					// URL is unsafe
					// It's possible to get an empty $body, in which case (elseif above) we ignore the result
					$result = "Malware";
					$message .= $prefix . "This link from the main page needs attention: $checkLink... Google Safe Browsing says \"$body\"\n";
					$prefix = INDENT;
				}
			}
			else {
//// Long report
//				echoIfVerbose("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " URL: $checkLink Base was: $content \n");
//				writeLogAlert("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " URL: $checkLink Base was: $content");
//// Short report
				echoIfVerbose("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " URL: $checkLink \n");
				writeLogAlert("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " URL: $checkLink");
			}
		}
		catch (Exception $x) {
		}
	}
	if ($result == "OK") {
		$message .= "GSB reports all is OK\n";
	}
	$message .= INDENT . "  The page contains " . count($links) . " relevant links.";
	$message .= INDENT . "  $numberOfChecks GSB inquiries were made.";

	// Nothing to save for GSB filter
//	$privateStore[$filterName][$url]['lengths'] = $lengthsString;

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function gsbInit($content, $args, $privateStore) {
	$filterName = "gsb";
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
function gsbDestroy($content, $args, $privateStore) {
	$filterName = "gsb";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function gsb($args) {
	$filterName = "gsb";
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

/////////////////////////////////////////////////////////////////////////////////////////////////// 
/////////////////////////////////////////////////////////////////////////////////////////////////// 
// These functions are used by gsb2 and gsbdaily (and perhaps other) filters
// DO NOT DELETE THEM and do not make changes without considering their possible effects
// on those other filters.
function extractLinks($tag, $refs, $dom, $links) {
	if (!is_array($refs)) {
		$refs = array($refs);
	}
	try {
		$elements = $dom->getElementsByTagName($tag);
		foreach($elements as $oneElement) {
			foreach($refs as $ref) {
				$possibleLink = $oneElement->getAttribute($ref);
				$possibleLower = strtolower($possibleLink);
$possibleLower = str_replace(array("\r","\n","\t","<br"), "", $possibleLower);
				// Fix double "http://" (surprisingly this is common)
				// And believe it or not, browsers will "correct it" and follow the link
				if (strncmp($possibleLower, "http://http://", 14) == 0) {
					$possibleLink = substr($possibleLink, 7);
				}
				// Don't try to use bare "http://" (surprisingly this is common)
				if ($possibleLower == "http://") {
					continue;
				}
				// Don't use degenerate "TLD-like" domain name that contains no dot (surprisingly this is common)
				if (strpos($possibleLower, '.') === false) {
					continue;
				}
				
				// Only keep if "http://" - in other words, an "external" link
				if (strncmp($possibleLower, "http://", 7) == 0) {
					// Encode non-URL chars, particulary don't want blanks
					$possibleLower = urlencode($possibleLower);
					// Truncate (from right) to remove filename
					$slashPos = strripos($possibleLink, "/");
					if ($slashPos > 6) {
						$possibleLink = substr($possibleLink, 0, $slashPos+1);
					}
					// Don't duplicate links
					if (in_array($possibleLink, $links)) {
						continue;
					}
					// Keep it
					$links[] = $possibleLink;
					echoIfVerbose("$tag  $ref  $possibleLink  \n");
				}
			}
		}
	}
	catch (Exception $x) {
			echoIfVerbose("In extractLinks Exception: " . $x->getMessage() . " $url\n");  // use $url not $das
			writeLogAlert("In extractLinks Exception: " . $x->getMessage() . " $url\n");  // use $url not $das
	}
	return $links;
}

///////////////////////////////// 
function domainAndSubdirs($url) {
	$result = $url;
	if (strncmp(strtolower($result), "http://", 7) == 0) {
		// Truncate (from right) to remove filename
		$slashPos = strrpos($result, "/");
		if ($slashPos > 6) {
			$result = substr($result, 0, $slashPos+1);
		}
		// Truncate (from right) to remove after "?" (which still might be there after stripping "/")
		// This happens often in the case of a CGI "?" with an "http://" parameter after it
		$qPos = strrpos($result, "?");
		if ($qPos !== false) {
			$result = substr($result, 0, $qPos+1);
		}
	}
	return $result;
}

///////////////////////////////// 
function breadcrumbsString($howToGetThere, $finalURL="") {
	$top = count($howToGetThere);
	$i = 0;
	$mess = "";
	while ($i < $top) {
		$mess .= $howToGetThere[$i++] . " -> ";
	}
	$mess .= " $finalURL";
	return $mess;
}

///////////////////////////////// 
function gsbCheckURL($args, $url, $numberOfChecks, $failures, $prefix, $checkedURLs, $checkedDomains, &$howToGetThere) {
	
	$result = "OK";
	$message = "";
	
	try {
		$das = str_replace(' ', '+', domainAndSubdirs($url));
		echoIfVerbose("gsbCheckURL($url) in domain " . $das . " \n");
		if ( in_array($das, $checkedDomains) ) {
			echoIfVerbose("Already checked the domain for $url \n");
			return array($result, $message);
		}
		
		// Add to array of "already checked" links
		$checkedDomains[] = $das;
		
		$httpResult = httpGet($args['gsbserver'] . "/gsb-check?url=$das", $args['useragent'], 15);
		$numberOfChecks++;
		if (isset($httpResult['code']) && ($httpResult['code'] == 200)) {
			$body = $httpResult['body'];
			echoIfVerbose("$body \n");
			if (strncmp(strtoupper($body), "OK", 2) == 0) {
				// URL is safe
				$result = "OK";
				echoIfVerbose("  OK \n");
			}
			else {
				if (isset($body) && (strpos($body, 'goog-')!==false) ) {
					// goog-malware-shavar   (malware)
					// goog-                 (phishing)
					// ($body is the response GSB gave us)
					// URL is unsafe
					$result = "Malware";
					$message .= $prefix . "Google Safe Browsing reports a problem with this URL: $das Problem description: $body  \n";
					$prefix = INDENT;
					$message .= $prefix . "How we got there: " . breadcrumbsString($howToGetThere, $url) . " \n";
					echoIfVerbose("  BAD $body \n");
				}
			}
		}
		else {
			$bcs = breadcrumbsString($howToGetThere, $das);
			echoIfVerbose("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " Looking up: $das How we got there: $bcs\n");
			writeLogAlert("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " Looking up: $das How we got there: $bcs");
			$prefix = INDENT;
			$failures++;

		}
	}
	catch (Exception $x) {
		$bcs = breadcrumbsString($howToGetThere, $das);
		echoIfVerbose("In gsbCheckURL Exception: " . $x->getMessage() . " $das How we got there: $url\n");  // use $url not $das
		writeLogAlert("In gsbCheckURL Exception: " . $x->getMessage() . " $das How we got there: $url\n");  // use $url not $das
	}
	
	return array($result, $message);
}

///////////////////////////////// 
function keepAliveURL($numberOfChecks, $url) {
	global $properties;
	// Every 100th time, update our URL keepalive file (indicates we are still processing)
	if (($numberOfChecks == 0) || ($numberOfChecks%100)) {
		return;
	}
	if (isset($properties['urlfilename'])) {
		$urlFileName = $properties['urlfilename'];
		if ($urlFileName != null && strlen($urlFileName) > 0) {
			echoIfVerbose(" GSB filter is updating URL file to say '$url $numberOfChecks'\n");
			@file_put_contents($urlFileName, $url);
		}
	}
}

///////////////////////////////// 
function gsbExploreLinks($args, $url, $depth, $maxDepth, $numberOfChecks, $failures, $prefix, $checkedURLs, $checkedDomains, $howToGetThere) {
	
	$result = "OK";
	$message = "";
	echoIfVerbose ("Reached depth $depth with maximum $maxDepth \n");
	if ($depth == $maxDepth) {
		return array($result, $message);
	}
	echoIfVerbose ("gsbExploreLinks($url) at depth $depth \n");
	array_push($howToGetThere, $url);
	$link = "";						// for proper scope
	try {
		// Get the contents of the page
		$httpResult = httpGet($url, $args['useragent'], 15);
		$numberOfChecks++;
		if (isset($httpResult['code']) && ($httpResult['code'] == 200)) {
			$body = $httpResult['body'];
			
			if (strlen($body) < GSB_PAGE_SIZE_LIMIT) {
				echoIfVerbose ("GSB checking $url \n");
				$dom = new DOMDocument();
				@$dom->loadHTML($body);

				// Find all the links on the page
				$links = extractLinks("a", "href", $dom, array());
				$links = extractLinks("form", "action", $dom, $links);
				$links = extractLinks("img", "src", $dom, $links);
				$links = extractLinks("link", array("rel","href"), $dom, $links);
				$links = extractLinks("script", "src", $dom, $links);
				echoIfVerbose (count($links)." links to explore \n");

				foreach ($links as $link) {
					keepAliveURL($numberOfChecks, $link);
					if (in_array($link, $checkedURLs) ) {
						echoIfVerbose("Already checked $link \n");
						continue;
					}

					// Remove leading underscores. Shouldn't be any, but sometimes there are. Life's a mystery. 2013-05-28 sky
					// If you leave one, GSB will fail.
					$link = ltrim($link, '_');
					// Check this link against GSB. The remote GSB server, in Python, keeps track of malware and phishing domains.
					list($r, $mess) = gsbCheckURL(&$args, $link, &$numberOfChecks, &$failures, &$prefix, &$checkedURLs, &$checkedDomains, &$howToGetThere);
					if ($r != "OK") {
						$result = $r;
					}
					$message .= $mess;
					
					// Go deeper
					if (($depth+1) < $maxDepth) {
						echoIfVerbose ("Going to depth " . ($depth+1) . " maximum " . $maxDepth . "\n");
						list($r, $mess) = gsbExploreLinks(&$args, $link, $depth+1, $maxDepth, &$numberOfChecks, &$failures, &$prefix, &$checkedURLs, &$checkedDomains, &$howToGetThere);
						echoIfVerbose ("Done at depth " . ($depth+1) . " \n");
						if ($r != "OK") {
							$result = $r;
						}
						$message .= $mess; 
					}				
				}
			}
			else {
					// Page is really too large. This might be just a technical problem, but
					// we can't trust the "DOMDocument" routines to function on large pages (they go
					// into 100% CPU loop sometimes), so we will not check overly-large pages.
					$das = str_replace(' ', '+', domainAndSubdirs($url));
					$bcs = breadcrumbsString($howToGetThere, $das);
					echoIfVerbose ("Page ($url) is too large (".strlen($body).") to check programmatically. How we got there: $bcs\n");
					$message .= "Page ($url) is really too large (".strlen($body).") to check programmatically. You might want to check manually. How we got there: $bcs\n";
			}
		}
	}
	catch (Exception $x) {
		$bcs = breadcrumbsString($howToGetThere, $das);
		echoIfVerbose("In gsbCheckURL Exception: " . $x->getMessage() . " $das How we got there: $bcs\n");
		writeLogAlert("In gsbCheckURL Exception: " . $x->getMessage() . " $das How we got there: $bcs\n");
	}

	array_pop($howToGetThere);
	
	return array($result, $message);
}
?>