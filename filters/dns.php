<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: dns
		Checks certain DNS entries and reports when they change.
	*/

	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";
include_once "include/functions.inc";

///////////////////////////////// 
function dnsScan($content, $args, $privateStore) {
	$filterName = "dns";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

	echoIfVerbose("The [dns] filter was invoked \n");
	$domain = domainOnly($url);
	$fqdn   = fqdnOnly($url);
	echoIfVerbose("Checking $domain \n");
	
	// NOTE: if more than one url= directive uses the [dns] condition, it will really only report
	//   changes for the first url= line that contains 'dns'
	$newURL = !isset($privateStore[$filterName][$domain]['soa']) || (strlen($privateStore[$filterName][$domain]['soa']) == 0);
	echoIfVerbose("New domain? $newURL \n");
	
	///////////////////////////////// 
	// General strategy:
	//   First check SOA
	
	
	try {
		$isSOA = checkdnsrr($domain, "SOA");
		echoIfVerbose("SOA? $isSOA \n");
	
//	DNS_A, DNS_CNAME, DNS_HINFO, DNS_MX, DNS_NS, DNS_PTR, DNS_SOA, DNS_TXT, DNS_AAAA, DNS_SRV, 
//  DNS_NAPTR, DNS_A6, DNS_ALL or DNS_ANY
//		$da = dns_get_record($domain, DNS_ALL);
//	print_rIfVerbose($da);
	
		////// SOA
		$da = dns_get_record($domain, DNS_SOA);
		if ($da !== false) {
			$da0 = $da[0];
			// Note: don't include ttl because it counts down dynamically - everything else can be used
			$soa = $da0['host']." ".$da0['class']." ".$da0['type']." ".$da0['mname'].". ".$da0['rname'].". serial:".$da0['serial']." refresh:".$da0['refresh']." retry:".$da0['retry']." expire:".$da0['expire']." min-ttl:".$da0['minimum-ttl'];
			echoIfVerbose("$soa \n");
			if (strcasecmp($soa,$privateStore[$filterName][$domain][DNS_SOA]) != 0) {
				$result = "DNS";
				$message .= "SOA changed from \"" . $privateStore[$filterName][$domain][DNS_SOA] ."\" To \"$soa\"\n";
			}
			$privateStore[$filterName][$domain][DNS_SOA] = $soa;	
	
			////// MX
			list ($message, $result) = checkEntriesByType($domain, DNS_MX, "MX", $privateStore, $filterName, $message, 'target');
		
			////// NS
			list ($message, $result) = checkEntriesByType($domain, DNS_NS, "NS", $privateStore, $filterName, $message, 'target');
		
			////// TXT
			list ($message, $result) = checkEntriesByType($domain, DNS_TXT, "TXT", $privateStore, $filterName, $message, 'txt');
		
			////// A     (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_A, "A", $privateStore, $filterName, $message, array('host', 'ip'));
		
			////// AAAA  (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_AAAA, "AAAA", $privateStore, $filterName, $message, array('host', 'ip'));

			////// Note that the dns_get_record() function has constants defined for certain types of records.
			//     There may be constants that were not available at the time of this writing.
		}
	}
	catch (Exception $dax) {
		$result = "DNS";
		$message .= INDENT . "Exception in filters:dns:dnsScan() $dax \n";
		echoIfVerbose("Exception in fliters:dns:dnsScan() $dax\n");	
	}


	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function dnsInit($content, $args, $privateStore) {
	$filterName = "dns";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$result   = "OK";						// default result
	$message = "[$filterName] Initialized. URL is " . $args['url'];

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function dnsDestroy($content, $args, $privateStore) {
	$filterName = "dns";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$result   = "OK";						// default result
	$message = "[$filterName] Shut down.";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function dns($args) {
	$filterName = "dns";
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
function checkEntriesByType($domain, $type, $typeString, &$privateStore, $filterName, $message, $keyField, $keyExtra=null) {
	// Get all records of a particular type that exist in the domain's DNS
	$da = dns_get_record($domain, $type);
	echoIfVerbose("$typeString count: " . count($da) . "\n");
	$result = "OK";
	try {
		if (isset($da) && (count($da) > 0)) {
			if (!isset($privateStore[$filterName][$domain][$typeString])) {
				$result = "DNS";
				$message .= INDENT . "$typeString records are being seen for the first time.\n";
				echoIfVerbose("$typeString records are being seen for the first time.\n");	
			}
			// Build array of current entries (which data depends on parameter $keyField)
			$mx = array();
			// $da contains (array) the records of one particular type
			foreach ($da as $dmx) {
				// $dmx is one single DNS entry
				// It is indexed somewhat like this; may vary.
				// Our caller has requested certain ones (in $keyField) and we can
				//   only retrive them if they exist.
				//      [host] => php.net
            	//		[type] => MX
            	//		[pri] => 5
            	//		[target] => pair2.php.net
            	//		[class] => IN
            	//		[ttl] => 6765
            	//		[ip] => 64.246.30.37
				//  Just examples.
				$keyFieldString = "";
				if (is_array($keyField)) {
					foreach($keyField as $kf) {
						if (isset($dmx[$kf])) {
							$keyFieldString .= $dmx[$kf] . ' ';
						}
					}
				}
				else {
					if (isset($dmx[$keyField])) {
						$keyFieldString = $dmx[$keyField];
					}
				}
				// Force lowercase because DNS records don't care about case
				$keyFieldString = strtolower($keyFieldString);
				if (!in_array($keyFieldString, $mx)) {
					$mx[] = $keyFieldString;
				}
			}
			// Note any "new" records
			// array_diff() gets us all members of $mx that are NOT in the private store from earlier
			if (isset($privateStore[$filterName][$domain][$typeString])) {
				$newMX = array_diff($mx, $privateStore[$filterName][$domain][$typeString]);
				foreach ($newMX as $dms) {
					$result = "DNS";
					$message .= INDENT . "New $typeString record: $dms \n";
					echoIfVerbose("New $typeString record: $dms\n");	
				}
				// Note any disappeared records
				$goneMX = array_diff($privateStore[$filterName][$domain][$typeString], $mx);
				foreach ($goneMX as $dms) {
					$result = "DNS";
					$message .= INDENT . "$typeString record deleted: $dms \n";
					echoIfVerbose("$typeString deleted: $dms\n");	
				}
			}
			else {
				foreach ($mx as $dms) {
					$result = "DNS";
					$message .= INDENT . "$typeString record: $dms \n";
					echoIfVerbose("$typeString record: $dms\n");	
				}
			}
			// Save the current MX server names
			$privateStore[$filterName][$domain][$typeString] = $mx;
		}
	}
	catch (Exception $x) {
		$result = "DNSexcep";
		$message .= "Exception in [$filterName] function checkEntriesByType($typeString): " . $x->getMessage() . "\n";
		echoIfVerbose("Exception in [$filterName] function checkEntriesByType($typeString): " . $x->getMessage() . "\n");
	}
	return array($message, $result);
}

?>