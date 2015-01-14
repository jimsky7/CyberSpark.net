<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: asn
		Checks this page against Team Cymru ASN API.
			http://www.team-cymru.org/Services/ip-to-asn.html
	**/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "cyberspark.sysdefs.php";
include_once "include/echolog.inc";
include_once "include/http.inc";
include_once "include/functions.inc";

///////////////////////////////// 
// getASNinfo()
// Use the Team-Cymru API to get ASN information
// This is highly dependent upon an undocumented HTTP form.
function getASNinfo($url) {
	$paramArray = array();

	$paramArray['action'] = 'do_whois';
	$paramArray['family'] = 'ipv4';
	$paramArray['method_whois'] = 'whois';

	$paramArray['bulk_paste'] = '8.8.8.8';
	$ip = getIPforHost(hostname($url));
	if ($ip != null) {
		$paramArray['bulk_paste'] = $ip;
	}
	$paramArray['submit_paste'] = 'Submit';

	///////////////////////////////// 
	if (defined('ASN_SERVER')) {
		$APIurl    = ASN_SERVER;
	}
	else {
		$APIurl    = 'https://asn.cymru.com/cgi-bin/whois.cgi';
	}
	if (defined('DEFAULT_IDENTITY') && defined ('DEFAULT_USERAGENT')) {
		$userAgent = DEFAULT_USERAGENT . ' ' . DEFAULT_IDENTITY;
	}
	else {
		$userAgent = 'http://CyberSpark.net/agent info@cyberspark.net';
	}
	$timeout   = 300;
	
	$r = curlPost($APIurl, $userAgent, $paramArray, $timeout, $auth=null, $sslVerify=false, $options=null);

	// Parse the result from the Team-cymru API
	// This might change without notice.
	$i = stripos($r['body'], '<PRE>');
	if ($i !== false) {
		$j = stripos($r['body'], '</PRE>', $i);
		if ($j !== false) {
			$rx = substr($r['body'], ($i+5), ($j-$i-5));
			$e = explode("\n", $rx);
			$x = explode('|', $e[3]);
			$result = array();
			$result['asn']      = trim($x[0]);
			$result['ip']       = trim($x[1]);
			$result['operator'] = trim($x[2]);	
			$result['latency']  = $r['curl_info']['total_time'];
			return $result;	
		}
	}
	else {
	}

	return null;
}

///////////////////////////////// 
function asnScan($content, $args, $privateStore) {
	$filterName = 'asn';
	$result     = 'OK';						// default result
	$url        = $args['url'];
	$message    = '';

	if (isset($args['notify']) && isset($privateStore[$filterName][$url][$filterName.'_ran_today'])
		&& isNotifyHour($args['notify']) && !$privateStore[$filterName][$url][$filterName.'_ran_today']) {
		$result = 'OK';
		$asnInfo = getASNinfo($url);
		$host = hostname($url);
		if ($asnInfo != null) {
			$message .= "\n";
			// Warnings needed?
			if (isset($privateStore[$filterName][$url]['ip'])) {
				if ($privateStore[$filterName][$url]['asn'] != $asnInfo['asn']) {
					$result = 'Critical';
					$message .= "ASN information changed! \n";
				}
				else if (strcasecmp($privateStore[$filterName][$url]['operator'], $asnInfo['operator']) != 0) {
					$result   = 'Critical';
					$message .= "ASN information changed! \n";
				}
			}
			else {
				$result = 'Warning';
				$message .= "ASN information is available for the first time. (This is good.)\n";
			}
			// Document the current values in the message
			$message .= INDENT . "FQDN $host\n";
			$message .= INDENT . "ASN $asnInfo[asn] operated by '$asnInfo[operator]'\n";
			$message .= INDENT . "IP is $asnInfo[ip]\n";
			$message .= INDENT . "API latency: $asnInfo[latency]\n";
			$message .= INDENT . "Data is from Team Cymru http://www.team-cymru.org/Services/ip-to-asn.html\n";
			// While debugging...
			echoIfVerbose(" ASN information for $url \n");
			echoIfVerbose(" ASN: $asnInfo[asn] IP: $asnInfo[ip] Latency: $asnInfo[latency] \n");
			echoIfVerbose(" Operator: $asnInfo[operator] \n");
			// Save current values for next time
			$privateStore[$filterName][$url]['asn']      = $asnInfo['asn'];
			$privateStore[$filterName][$url]['ip']       = $asnInfo['ip'];
			$privateStore[$filterName][$url]['operator'] = $asnInfo['operator'];
			$privateStore[$filterName][$url]['latency']  = $asnInfo['latency'];
		}
		else {
			$message .= "Could not retrieve ASN info for '$host'\n";
			echoIfVerbose(" Could not retrieve ASN info for '$host' \n");
		}
	}
	else {
		// Clear the 'flag' that says we've sent today's notification. So it can be sent tomorrow.
		$privateStore[$filterName][$url]['asn_ran_today'] = false;
		echoIfVerbose(" The asn filter only runs once  day and this is not that time. \n");
		// Add some ASN information even though the result is "OK" and the filter isn't really running
		if (isset($privateStore[$filterName][$url]['asn'])) {
			$message .= " ASN: ".$privateStore[$filterName][$url]['asn'];
		}
		if (isset($privateStore[$filterName][$url]['operator'])) {
			$message .= " Operator: ".$privateStore[$filterName][$url]['operator'];
		}
		$message .= " (thx to Team-Cymru ASN API)";
	}
	
	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function asnInit($content, $args, $privateStore) {
	$filterName = "asn";
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
function asnDestroy($content, $args, $privateStore) {
	$filterName = "asn";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function asn($args) {
	$filterName = "asn";
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

?>