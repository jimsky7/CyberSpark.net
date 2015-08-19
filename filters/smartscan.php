<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: smartscan
		Reduces a page to <frame> and <script> and <object> and <embed>
		elements, then hashes them and warns if the hash changes between
		runs.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.php";
include_once "include/functions.php";
include_once "include/filter_functions.php";

define('SMARTSCAN_MAX_ALERTS', 2);

///////////////////////////////// 
function smartscanScan($content, $args, $privateStore) {
	$filterName = "smartscan";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";
	$lengthsString = '';

	// Remove chars that don't make any difference and can get in the way
	$content = str_replace(array("\r","\n","\t"), "", $content);
	$contentLength = strlen($content);
	
	// Get the pertinent HTML tags
	echoIfVerbose("SMARTSCAN \n");


	// Set up a "consecutive alert" counter. It will reset itself only once a day.
	$alertCount = 0;
	if (!isset($privateStore[$filterName][$url]['alertcount']) || isNotifyHour($args['notify'])) {
		echoIfVerbose("Reset alerts to zero in [$filterName]\n");
	}
	else {
		// Exists
		$alertCount = $privateStore[$filterName][$url]['alertcount'];
	}

	///////////////////////////////// 
	// If we haven't already sent enough alerts... 
	if ($alertCount < SMARTSCAN_MAX_ALERTS) { 

		///////////////////////////////// 
		// Look at <html></html> blocks
		
		// There may be multiple <html></html> blocks within a page, which isn't proper form, 
		//   but is tolerated by browsers.
		$firstHTMLPosition   = stripos($content, "</html>");	// not case-sensitive, AND blanks already were stripped
		if ($firstHTMLPosition === false) {
			// The page does not contain a closing </HTML>
			$alertCount++;
			echoIfVerbose("Alert count $alertCount in [$filterName]\n");
			$result = "Advice";
			$message .= "This page doesn't contain a closing </HTML> tag,\n";
			$message .= INDENT . "  or there may be a problem with the sequence of the tags\n";
			$message .= INDENT . "  <HTML><HEAD></HEAD><BODY></BODY></HTML> on this page. \n";
			$message .= INDENT . "  Please fix this to ensure proper monitoring and browser rendering. \n";
			if ($alertCount == SMARTSCAN_MAX_ALERTS) {
				$message .= "            Similar alerts will be suppressed for up to 24 hours. \n";
			}
		}
		else {
			// There is at least one </HTML>
			$lastHTMLPosition = strripos($content, "</html>", $firstHTMLPosition);
//			if ($firstHTMLPosition != $lastHTMLPosition) {
//				$alertCount++;
//				$result = "Advice";
//				$message .= "This page contains multiple <HTML></HTML> blocks. \n";
//				$message .= "            Please fix this to ensure proper monitoring and browser rendering. \n";
//				echoIfVerbose("$message\n)";
//			}
			if ($lastHTMLPosition < ($contentLength - 7)) {
				// There's at least one character after the last </html>
				$excess = substr($content, $lastHTMLPosition+7);
				if (stripos($excess, "script") !== false) {
					$alertCount++;
					echoIfVerbose("Alert count $alertCount in [$filterName]\n");
					$result = "Warning";
					$message .= "There's probably a SCRIPT after the closing </HTML> on this page. \n";
					$message .= INDENT . "  Please fix this to ensure proper monitoring and browser rendering. \n";
					$message .= INDENT . "  The script MAY be malware, so check it carefully. \n";
					if ($alertCount == SMARTSCAN_MAX_ALERTS) {
						$message .= "            Similar alerts will be suppressed for up to 24 hours. \n";
					}
					$message .= INDENT . "  Here is the code that appears at the end of the page. \n";
					$message .= INDENT . "  Do NOT click any links that appear in the code below. \n";
					$message .= INDENT . "  '<' and '>' have been replaced by '{' and '}' for safety. \n";
					$excess  = str_replace(array('<', '>'), array('{', '}'), $excess);
					$message .= INDENT . "  $excess \n";
					echoIfVerbose("$message\n)");
				}
			}
		}
		
		// Check for <SCRIPT> before opening <HTML>
		$firstHTMLPosition   = stripos($content, "<html");	// not case-sensitive, AND blanks already were stripped
		$firstSCRIPTPosition = stripos($content, "<script");	// not case-sensitive, AND blanks already were stripped
		if (($firstHTMLPosition !== false) && ($firstSCRIPTPosition !== false) && ($firstSCRIPTPosition < $firstHTMLPosition)) {
			// There's javascript before the opening <HTML>
			$alertCount++;
			echoIfVerbose("Alert count $alertCount in [$filterName]\n");
			$result = "Warning";
			$message .= "There's a SCRIPT before the opening <HTML> on this page. \n";
			$message .= INDENT . "  This almost certainly indicates the presence of malware. \n";
			if ($alertCount == SMARTSCAN_MAX_ALERTS) {
				$message .= INDENT . "  Similar alerts will be suppressed for up to 24 hours. \n";
			}
			$message .= INDENT . "  Here is the code that appears at the beginning of the page. \n";
			$message .= INDENT . "  Do NOT click any links that appear in the code below. \n";
			$message .= INDENT . "  '<' and '>' have been replaced by '{' and '}' for safety. \n";
			$excess  = str_replace(array('<', '>'), array('{', '}'), substr($content, 0, $firstHTMLPosition));
			$message .= INDENT . "  $excess \n";
			echoIfVerbose("$message\n)");
		}
	}
	else {
		echoIfVerbose("Alerts suppressed (overlimit) in [$filterName]\n");
	}
	$privateStore[$filterName][$url]['alertcount'] = $alertCount;	


	///////////////////////////////// 
	// Parse DOM to be used later in analysis
	$dom = new DOMDocument();
	@$dom->loadHTML($content);

	///////////////////////////////// 
	// Check 'meta' 'generator' if one is present within this page
	$genContent = extractHTMLsubtag('meta', 'name', 'generator', 'content', $dom);
	if ($genContent != null) {
		if (isset($privateStore[$filterName][$url]['generator'])) {
			// Check against previous generator name
			$s = $privateStore[$filterName][$url]['generator'];
			if (strcmp($s, $genContent) != 0) {
				// Changed
				if ($result != "OK") {
					// Some message was inserted above, so need extra space
					$message .= "          ";
				}
				$message .= INDENT."Smart scan says <meta name='generator'> has changed to '$genContent'\n";
				$message .= INDENT."Smart scan says <meta name='generator'> was previously '$s'\n";
				$result = "Warning";
				$privateStore[$filterName][$url]['generator'] = $genContent;
			}
		}
		else {
			// New generator name
			$privateStore[$filterName][$url]['generator'] = $genContent;
			$message .= INDENT."Smart scan says <meta name='generator'> has appeared '$genContent'\n";
			$result = "Warning";
		}	
	}

	///////////////////////////////// 
	// Check 'Open Graph' (og:) if any are present within this page
	$ogContent = '';
	$s = extractHTMLsubtag('meta', 'property', 'og:locale', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:locale='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:type', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:type='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:title', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:title='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:url', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:url='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:site_name', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:site_name='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:image', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:image='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:audio', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:audio='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:description', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:description='.$s.'|';
	}
	$s = extractHTMLsubtag('meta', 'property', 'og:video', 'content', $dom);
	if ($s != null) {
		$ogContent .= 'og:video='.$s.'|';
	}
	if (strlen($ogContent) > 0) {
		if (isset($privateStore[$filterName][$url]['og'])) {
			// Check against previous OG info
			$s = $privateStore[$filterName][$url]['og'];
			if (strcmp($s, $ogContent) != 0) {
				// Changed
				if ($result != "OK") {
					// Some message was inserted above, so need extra space
					$message .= "          ";
				}
				$message .= INDENT."Smart scan says Open Graph <meta name='og:'> items have changed to '$ogContent'\n";
				$message .= INDENT."Smart scan says Open Graph <meta name='og:'> items were previously '$s'\n";
				$result = "Warning";
				$privateStore[$filterName][$url]['og'] = $ogContent;
			}
		}
		else {
			// New og items
			$privateStore[$filterName][$url]['og'] = $ogContent;
			$message .= INDENT."Smart scan says Open Graph <meta name='og:'> items have appeared '$ogContent'\n";
			$result = "Warning";
		}	
	}

	///////////////////////////////// 
	// Check 'google-site-verification' if one is present within this page
	$gsvContent = extractHTMLsubtag('meta', 'name', 'google-site-verification', 'content', $dom);
	if ($gsvContent != null) {
		if (isset($privateStore[$filterName][$url]['gsv'])) {
			// Check against previous generator name
			$s = $privateStore[$filterName][$url]['gsv'];
			if (strcmp($s, $gsvContent) != 0) {
				// Changed
				if ($result != "OK") {
					// Some message was inserted above, so need extra space
					$message .= "          ";
				}
				$message .= INDENT."Smart scan says <meta name='google-site-verification'> has changed to '$gsvContent'\n";
				$message .= INDENT."Smart scan says <meta name='google-site-verification'> was previously '$s'\n";
				$result = "Warning";
				$privateStore[$filterName][$url]['gsv'] = $gsvContent;
			}
		}
		else {
			// New generator name
			$privateStore[$filterName][$url]['gsv'] = $gsvContent;
			$message .= INDENT."Smart scan says <meta name='google-site-verification'> has appeared '$gsvContent'\n";
			$result = "Warning";
		}	
	}

	///////////////////////////////// 
	// Check for changes in SCRIPT or IFRAMEs within this page
	//   Thanks for the DOM suggestion! - see
	//     http://w-shadow.com/blog/2009/10/20/how-to-extract-html-tags-and-their-attributes-with-php/
	$smartContent = "";
	$smartContent .= extractHTMLtags("script", array("src", "type", "language"), $dom);
	$smartContent .= extractHTMLtags("iframe", "src", $dom);
	echoIfVerbose($smartContent . "\n");
	
	// Check against previous
	$contentLength = strlen($smartContent);

	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	// See whether length has changed since last time
	if (isset($privateStore[$filterName][$url]['lengths'])) {
		// This URL has some length(s) recorded from previous examination(s)
		list($lengthsString, $lengths) = limitLengths($privateStore[$filterName][$url]['lengths'], MAX_LENGTHS);

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
			if ($result != "OK") {
				// Some message was inserted above, so need extra space
				$message .= "          ";
			}
			$message .= "Smart scan indicates script or iframe content has changed length to " . $contentLength . "\n            Previous lengths include [" . $lengthsString . "]";
			$result = "Critical";
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
	$privateStore[$filterName][$url]['lengths'] = $lengthsString;

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function smartscanInit($content, $args, $privateStore) {
	$filterName = "smartscan";
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
function smartscanDestroy($content, $args, $privateStore) {
	$filterName = "smartscan";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function smartscan($args) {
	$filterName = "smartscan";
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

///////////////////////////////// 
///////////////////////////////// 
function extractHTMLtags($tag, $subs, $dom) {
	if (!is_array($subs)) {
		$subs = array($subs);
	}
	$elements = $dom->getElementsByTagName($tag);
	$tagContent = '';
	foreach($elements as $element) {
		$tagContent .= "<$tag>";
		foreach ($subs as $sub) {
			if ($element->hasAttribute($sub)) {
				$tagContent .= $element->getAttribute($sub) . "|";
			}
		}
		$tagContent .= $element->textContent;
		$tagContent .= "</$tag>";
	}
	return $tagContent;
}

///////////////////////////////// 
///////////////////////////////// 
function extractHTMLsubtag($tag, $sub, $subMatch, $subExtract, $dom) {
	// Example
	// extractHTMLsubtag('metta', 'name', 'generator', 'content', $dom);
// 	$genContent = extractHTMLsubtag('metta', 'name', 'generator', 'content', $dom);
	// looks for
	// <meta name='generator' content='xxxxxxx' />
	// and returns 'xxxxxxx'
	// return null if nothing found
	$elements = $dom->getElementsByTagName($tag);
	foreach($elements as $element) {
		if ($element->hasAttribute($sub)) {
			if (strcasecmp($element->getAttribute($sub), $subMatch) == 0) {
				if ($element->hasAttribute($subExtract)) {
					return $element->getAttribute($subExtract);
				}
			}
		}
	}
	return null;
} 

?>