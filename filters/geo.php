<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: geo
		Checks a site for geolocation information.
			https://github.com/fiorix/freegeoip/
	**/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "cyberspark.sysdefs.php";
include_once "include/echolog.php";
include_once "include/http.php";
include_once "include/functions.php";

///////////////////////////////// 
// getGeoInfo() » Use FREEGEOIP software we have installed on our own server
function getGeoInfo($url) {
	///////////////////////////////// 
	if (defined('GEO_SERVER')) {
		$APIurl    = GEO_SERVER;
	}
	else {
		$APIurl    = 'http://0.0.0.0:8080/json/';
	}
	if (defined('DEFAULT_IDENTITY') && defined ('DEFAULT_USERAGENT')) {
		$userAgent = DEFAULT_USERAGENT . ' ' . DEFAULT_IDENTITY;
	}
	else {
		$userAgent = 'http://CyberSpark.net/agent info@cyberspark.net';
	}

	$ip = getIPforHost(hostname($url));
	if ($ip == null) {
		$ip = '';
	}

	$APIurl = $APIurl . $ip;
	$paramArray = array();
	$timeout   = 300;

	$r = curlGet($APIurl, $userAgent, $paramArray, $timeout, $auth=null, $sslVerify=false, $options=null);

	if (is_array($r)) {
		return json_decode($r['body'], true);
		// A sample result from the API
		// Array ( 
		//		[ip] => 173.45.230.19 
		//		[country_code] => US 
		//		[country_name] => United States 
		//		[region_code] => TX 
		//		[region_name] => Texas 
		//		[city] => San Antonio 
		//		[zip_code] => 78218 
		//		[time_zone] => America/Chicago 
		//		[latitude] => 29.489 
		//		[longitude] => -98.399 
		//		[metro_code] => 641 ) 
	}
	return null;
}

///////////////////////////////// 
function geoScan($content, $args, $privateStore) {
	$filterName = 'geo';
	$attributeName = 'ip';
	$result     = 'OK';						// default result
	$url        = $args['url'];
	$message    = '';

	// Clear the 'flag' that says we've sent today's notification. So it can be sent tomorrow.
	$privateStore[$url][$filterName.'_ran_today'] = false;
	echoIfVerbose(" The $filterName filter only runs once  day and this is not that time. \n");
	// Add some GEO information even though the result is "OK" and the filter isn't really running
	if (isset($privateStore[$url][$attributeName])) {
		$message .= ' IP: '.$privateStore[$url][$attributeName];
		if (isset($privateStore[$url]['city'])) {
			$message .= ' City: '.$privateStore[$url]['city'];
		}
		if (isset($privateStore[$url]['metro_code'])) {
			$message .= ' Metro code: '.$privateStore[$url]['metro_code'];
		}
		$message .= " (thx to FREEGEOIP)";
	}
	
	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function geoNotify($content, $args, $privateStore) {
	$filterName = 'geo';
	$attributeName = 'ip';
	$result     = 'OK';						// default result
	$url        = $args['url'];
	$message    = '';

	if (isset($args['notify']) && isset($privateStore[$url][$filterName.'_ran_today']) && isNotifyHour($args['notify']) && !$privateStore[$url][$filterName.'_ran_today']) {
		$result = 'OK';
		$geoInfo = getGeoInfo($url);
		$host = hostname($url);

		if (($geoInfo != null) && isset($geoInfo['ip'])) {
			$message .= "\n";
			// Warnings needed?
			if (isset($privateStore[$url][$attributeName]) && isset($privateStore[$url]['metro_code'])) {
				if ($privateStore[$url]['metro_code'] != $geoInfo['metro_code']) {
					$result = 'Critical';
					$message .= INDENT . "Geolocation information changed! \n";
				}
			}
			else {
				$result = 'Warning';
				$message .= INDENT . "Geolocation information is available for the first time. (This is good.)\n";
			}
			// Document the current values in the message
			$message .= INDENT . "FQDN: $host\n";
			$message .= INDENT . "GEO:  $geoInfo[country_code] ($geoInfo[country_name]) $geoInfo[region_code] ($geoInfo[region_name]) $geoInfo[city] $geoInfo[zip_code] \n";
			$message .= INDENT . "TZ:   $geoInfo[time_zone]\n";
			$message .= INDENT . "IP:   $geoInfo[ip]\n";
			$message .= INDENT . "Lat/Lon: $geoInfo[latitude]/$geoInfo[longitude]\n";
			$message .= INDENT . "Metro code: $geoInfo[metro_code]\n";
			$message .= INDENT . "Data is from FREEGEOIP https://github.com/fiorix/freegeoip/\n";
			// Save current values for next time
			foreach ($geoInfo as $key => $value) {
				$privateStore[$url][$key] = $value;
			}
			echoIfVerbose($message);
		}
		else {
			$message .= "Could not retrieve ".strtoupper($filterName)." info for '$host'\n";
			echoIfVerbose(" Could not retrieve ".strtoupper($filterName)." info for '$host' \n");
		}
	}
	
	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function geoInit($content, $args, $privateStore) {
	$filterName = 'geo';
	$result   = "OK";						// default result
	$url = $args['url'];
//	$contentLength = strlen($content);
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Initialized. URL is " . $args['url'];

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function geoDestroy($content, $args, $privateStore) {
	$filterName = 'geo';
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function geo($args) {
	$filterName = 'geo';
 	if (!registerFilterHook($filterName, 'scan', $filterName.'Scan', 10)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
 	if (!registerFilterHook($filterName, 'notify', $filterName.'Notify', 10)) {
		echo "The filter '$filterName' was unable to add a 'Notify' hook. \n";	
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

?>