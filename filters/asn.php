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
global $path;
include_once $path."cyberspark.config.php";
include_once $path."cyberspark.sysdefs.php";
include_once $path."include/echolog.php";
include_once $path."include/http.php";
include_once $path."include/functions.php";

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

	// Interface may fail?
	if (isset($r['code']) && $r['code'] == 200) {
		// Parse the result from the Team-cymru API
		// This might change without notice.
// echoIfVerbose(" Cymru result: - - - - - - - - - - - - - - - - - - \n");
// echoIfVerbose(" $r[body] \n");
		$i = stripos($r['body'], '<PRE>');
		// Result is like MySQL table with column headers
		// Example:
		//   <PRE>AS | IP | AS Name\n
		//   19999 | 10.0.0.230 | RACKSPACE\n
		//   </PRE>
		if ($i !== false) {
			$j = stripos($r['body'], '</PRE>', $i);
			if ($j !== false) {
				$rx = substr($r['body'], ($i+5), ($j-$i-5));
				$e = explode("\n", $rx);
				// $e[0] contains headers, delimited by "|"
				// $e[1] contains data, delimited by "|"
// echoIfVerbose("0:$e[0] \n");
// echoIfVerbose("1:$e[1] \n");
				$x = explode('|', $e[1]);
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
	}
	else {
		echoIfVerbose("ASN interface failed. HTTP result code is $r[code] \n");
		echoIfVerbose("ASN interface URL is: $APIurl \n");
		echoIfVerbose($r['body']." \n");
	}


	return null;
}

///////////////////////////////// 
function asnScan($content, $args, $privateStore) {
	$filterName = 'asn';
	$attributeName = 'asn';
	$result     = 'OK';						// default result
	$url        = $args['url'];
	$message    = '';

	// Clear the 'flag' that says we've sent today's notification. So it can be sent tomorrow.
	$privateStore[$url][$filterName.'_ran_today'] = false;
	echoIfVerbose(" The $filterName filter only runs once a day and this is not that time. \n");
	// Add some ASN information even though the result is "OK" and the filter isn't really running
	if (isset($privateStore[$url][$attributeName])) {
		$message .= " ASN: ".$privateStore[$url][$attributeName];
		if (isset($privateStore[$url]['operator'])) {
			$message .= " Operator: ".$privateStore[$url]['operator'];
		}
		$message .= " (thx to Team-Cymru ASN API)";
	}
	else {
		// Info has not yet been saved for this domain
		// Add it to database
		$asnInfo = getASNinfo($url);
		$host = hostname($url);

		if ($asnInfo == null) {
			$result = 'Warning';
			$message .= " (Could not retrieve any ASN info for '$host' [1])";
			echoIfVerbose(" Could not retrieve any ASN info for '$host' [1]\n");
			$message .= INDENT . "Data is from Team Cymru http://www.team-cymru.org/Services/ip-to-asn.html and is checked once a day.";
		}
		else {
			$result = 'Warning';
			$message .= "\n";
			$message .= INDENT . "ASN information is available for the first time. (This is good.) [1]\n";
			$message .= INDENT . "You will be notified if ASN information changes.\n";
			$message .= INDENT . "Please also note that if ASN information disappears in the future, \n";
			$message .= INDENT . "or if the Team Cymru interface fails, you will not be alerted.\n";
			$message .= INDENT . "FQDN $host\n";
			$message .= INDENT . "ASN $asnInfo[asn] operated by '$asnInfo[operator]'\n";
			$message .= INDENT . "IP is $asnInfo[ip]\n";
			$message .= INDENT . "API latency: $asnInfo[latency]\n";
			$message .= INDENT . "Data is from Team Cymru http://www.team-cymru.org/Services/ip-to-asn.html and is checked once a day.";
			// While debugging...
			echoIfVerbose(" ASN information for $url [1]\n");
			echoIfVerbose(" ASN: $asnInfo[asn] IP: $asnInfo[ip] Latency: $asnInfo[latency] [1]\n");
			echoIfVerbose(" Operator: $asnInfo[operator] [1]\n");
			// Save current values for next time
			$privateStore[$url][$attributeName] = $asnInfo['asn'];
			$privateStore[$url]['ip']       	= $asnInfo['ip'];
			$privateStore[$url]['operator'] 	= $asnInfo['operator'];
			$privateStore[$url]['latency']  	= $asnInfo['latency'];
		}
	}
	
	$message = trim($message , "\n");				// remove any trailing LF
	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function asnNotify($content, $args, $privateStore) {
	$filterName = 'asn';
	$attributeName = 'asn';
	$result     = 'OK';						// default result
	$url        = $args['url'];
	$message    = '';

	if (isset($args['notify']) && isset($privateStore[$url][$filterName.'_ran_today']) && isNotifyHour($args['notify']) && !$privateStore[$url][$filterName.'_ran_today']) {
		$asnInfo = getASNinfo($url);
		$host = hostname($url);
		// NOTE: If ASN information exists but if 'operator' is an empty string, ignore the info.
		//       This eliminates false alarms when ASN 'operator' name goes empty, which it does a lot
		//       in the team cymru interface. Also, when operator is empty, the IP is typically also missing.
		// ALSO: If ASN information is not present, also ignore and don't report anything.
		if (($asnInfo != null) && (strlen($asnInfo['operator']) > 0)) {
			$message .= "\n";
			// Warnings needed?
			if (isset($privateStore[$url]['ip'])) {
				if ($privateStore[$url][$attributeName] != $asnInfo['asn']) {
					$result = 'Critical';
					$message .= INDENT . "ASN information changed! \n";
				}
				else if (strcasecmp($privateStore[$url]['operator'], $asnInfo['operator']) != 0) {
					$result   = 'Critical';
					$message .= INDENT . "ASN information changed! \n";
				}
			}
			else {
				$result = 'Warning';
				$message .= INDENT . "ASN information is available for the first time. (This is good.)\n";
				$message .= INDENT . "You will be notified if ASN information changes.\n";
				$message .= INDENT . "Please also note that if ASN information disappears in the future, \n";
				$message .= INDENT . "or if the Team Cymru interface fails, you will not be alerted.\n";
			}
			// Document the current values in the message
			$message .= INDENT . "FQDN $host\n";
			$message .= INDENT . "ASN $asnInfo[asn] operated by '$asnInfo[operator]'\n";
			$message .= INDENT . "IP is $asnInfo[ip]\n";
			$message .= INDENT . "API latency: $asnInfo[latency]\n";
			$message .= INDENT . "Data is from Team Cymru http://www.team-cymru.org/Services/ip-to-asn.html and is checked once a day.";
			// While debugging...
			echoIfVerbose(" ASN information for $url [2]\n");
			echoIfVerbose(" ASN: $asnInfo[asn] IP: $asnInfo[ip] Latency: $asnInfo[latency] [2]\n");
			echoIfVerbose(" Operator: $asnInfo[operator] [2]\n");
			// Save current values for next time
			$privateStore[$url][$attributeName] = $asnInfo['asn'];
			$privateStore[$url]['ip']       	= $asnInfo['ip'];
			$privateStore[$url]['operator'] 	= $asnInfo['operator'];
			$privateStore[$url]['latency']  	= $asnInfo['latency'];
		}
		else {
			$message .= "Could not retrieve ASN info for '$host' [2]\n";
			echoIfVerbose(" Could not retrieve ASN info for '$host' [2]\n");
		}
	}
	$message = trim($message , "\n");				// remove any trailing LF
	return array($message, $result, $privateStore);
}

///////////////////////////////// 
function asnInit($content, $args, $privateStore) {
	$filterName = 'asn';
	$attributeName = 'asn';
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
	$filterName = 'asn';
	$attributeName = 'asn';
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
	$filterName = 'asn';
 	$attributeName = 'asn';
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