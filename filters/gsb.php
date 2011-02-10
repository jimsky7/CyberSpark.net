<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: gsb
		Checks this page against Google Safe Browsing.
		In a more advanced mode, checks all links from this page as well.
	*/

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
		$checkLink = domainAndSubdirs($link);
		echoIfVerbose("GSB checking $checkLink\n");	
		try {
			$httpResult = httpGet($args['gsbserver'] . "/gsb-check?url=$checkLink", "", 15);
			$numberOfChecks++;
			if (isset($httpResult['code']) && ($httpResult['code'] == 200)) {
				$body = $httpResult['body'];
				echoIfVerbose("$body \n");
				if (strncmp(strtoupper($body), "OK", 2) == 0) {
					// URL is safe
				}
				else {
					// URL is unsafe
					$result = "Malware";
					$message .= $prefix . "This link from the main page needs attention: $checkLink... Google Safe Browsing says \"$body\"\n";
					$prefix = INDENT;
				}
			}
			else {
				echoIfVerbose("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " $checkLink Base was: $content \n");
// Probably remove these
//				$result = "Failure";
//				$message = $prefix . "GSB lookup failed. HTTP result code: " . $httpResult['code'] . " $checkLink  \n"; 
// And leave this in place
				writeLogAlert("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " $checkLink Base was: $content");
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
	$message = "";

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
 	if (!registerFilterHook($filterName, 'scan', 'gsbScan', 20)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', 'gsbInit', 20)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', 'gsbDestroy', 20)) {
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
		$das = domainAndSubdirs($url);
		echoIfVerbose("gsbCheckURL($url) in domain " . $das . " \n");
		if ( in_array($das, $checkedDomains) ) {
			echoIfVerbose("Already checked the domain for $url \n");
			return array($result, $message);
		}
		
		// Add to array of "already checked" links
		$checkedDomains[] = $das;
		
		$httpResult = httpGet($args['gsbserver'] . "/gsb-check?url=$das", "", 15);
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
					$message .= $prefix . "Google Safe Browsing reports a problem with $das  $body  \n";
					$prefix = INDENT;
					$message .= $prefix . "How we got there: " . breadcrumbsString($howToGetThere, $url) . " \n";
					echoIfVerbose("  BAD $body \n");
				}
			}
		}
		else {
			$bcs = breadcrumbsString($howToGetThere, $das);
			echoIfVerbose("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " $das How we got there: $bcs\n");
			writeLogAlert("GSB lookup failed. HTTP result code: " . $httpResult['code'] . " $das How we got there: $bcs");
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
		$httpResult = httpGet($url, "", 15);
		$numberOfChecks++;
		if (isset($httpResult['code']) && ($httpResult['code'] == 200)) {
			$body = $httpResult['body'];

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
				if (in_array($link, $checkedURLs) ) {
					echoIfVerbose("Already checked $link \n");
					continue;
				}

				// Check this link against GSB
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
	}
	catch (Exception $x) {
		$bcs = breadcrumbsString($howToGetThere, $das);
		echoIfVerbose("In gsbCheckURL Exception: " . $x->getMessage() . " $das How we got there: $link\n");
		writeLogAlert("In gsbCheckURL Exception: " . $x->getMessage() . " $das How we got there: $link\n");
	}

	array_pop($howToGetThere);
	
	return array($result, $message);
}
?>