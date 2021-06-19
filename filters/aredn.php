<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: aredn
		Examine certain page characteristics related to AREDN nodes.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
global $path;
include_once $path."cyberspark.config.php";

include_once $path."include/echolog.php";
include_once $path."include/functions.php";
include_once $path."include/filter_functions.php";

define('AREDN_MAX_ALERTS', 200);

///////////////////////////////// 
function arednScan($content, $args, $privateStore) {
	$filterName = "aredn";
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
	
	echoIfVerbose("AREDN \n");

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
	if ($alertCount < AREDN_MAX_ALERTS) {
		
		///////////////////////////////// 
		// AREDN node name
		$i = stripos($content, '<title>');
		if ($i >= 0) {
			$j = stripos($content, '</title>');
			if ($j >= 0) {
				$nodeName = substr($content, $i+7, $j-$i-7);
				// Truncate to first blank char
				$k = strpos($nodeName, ' ');
				if ($k >= 0) {
					$nodeName = substr($nodeName, 0, $k);
				}
				if (isset($privateStore[$filterName][$url]['nodename'])) {
					$previousNodeName= $privateStore[$filterName][$url]['nodename'];
					if (strcasecmp($nodeName, $previousNodeName) != 0) {
						$result = 'Alert';
						$alertCount++;
						$message .= "New node name:\n";
						$message .= INDENT . "  $nodeName\n";
						$message .= INDENT . "Previous node name was:\n";
						$message .= INDENT . "  $previousNodeName\n";
						$privateStore[$filterName][$url]['nodename'] = $nodeName;
					}
					else {
						// No change
					}
				}
				else {
					$privateStore[$filterName][$url]['nodename'] = $nodeName;
					$result = 'Alert';
					$message .= "The node name is:\n";
					$message .= INDENT . "  $nodeName\n";
				}
			}
		} 

		///////////////////////////////// 
		// Look at uptime
		$i = stripos($content, 'uptime');
		if ($i >= 0) {
			$j = stripos($content, '<td>', $i);
			if ($j >= 0) {
				$k = stripos($content, '<br>', $j);
				if ($k >= 0) {
					$uptime = substr($content, $j+4, ($k-$j-4));
					if (stripos($uptime, ',') !== false || stripos($uptime, ':') !== false) {
						// Contains : or ,
						$message .= "Uptime is $uptime\n";
					}
					else {
						$result = 'Alert';
						$alertCount++;
						$message .= "This node has rebooted in the last hour.\n";
						$message .= INDENT . "Uptime is $uptime\n";
					}
				}
			}
		}
		
		$privateStore[$filterName][$url]['alertcount'] = $alertCount;	
	}

	///////////////////////////////// 

	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.

	$message = trim($message , "\n");				// remove any trailing LF
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function arednInit($content, $args, $privateStore) {
	$filterName = "aredn";
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
function arednDestroy($content, $args, $privateStore) {
	$filterName = "aredn";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function aredn($args) {
	$filterName = "aredn";
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
//function extractHTMLtags($tag, $subs, $dom) {
//	if (!is_array($subs)) {
//		$subs = array($subs);
//	}
//	$elements = $dom->getElementsByTagName($tag);
//	$tagContent = '';
//	foreach($elements as $element) {
//		$tagContent .= "<$tag>";
//		foreach ($subs as $sub) {
//			if ($element->hasAttribute($sub)) {
//				$tagContent .= $element->getAttribute($sub) . "|";
//			}
//		}
//		$tagContent .= $element->textContent;
//		$tagContent .= "</$tag>";
//	}
//	return $tagContent;
//}

///////////////////////////////// 
///////////////////////////////// 
//function extractHTMLsubtag($tag, $sub, $subMatch, $subExtract, $dom) {
//	// Example
//	// extractHTMLsubtag('metta', 'name', 'generator', 'content', $dom);
//// 	$genContent = extractHTMLsubtag('metta', 'name', 'generator', 'content', $dom);
//	// looks for
//	// <meta name='generator' content='xxxxxxx' />
//	// and returns 'xxxxxxx'
//	// return null if nothing found
//	$elements = $dom->getElementsByTagName($tag);
//	foreach($elements as $element) {
//		if ($element->hasAttribute($sub)) {
//			if (strcasecmp($element->getAttribute($sub), $subMatch) == 0) {
//				if ($element->hasAttribute($subExtract)) {
//					return $element->getAttribute($subExtract);
//				}
//			}
//		}
//	}
//	return null;
//} 

?>
