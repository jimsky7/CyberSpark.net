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
global $path;
include_once $path."cyberspark.config.php";

include_once $path."include/echolog.php";
include_once $path."include/functions.php";

define ('SECONDS_PER_DAY', 86400);
define ('SECONDS_PER_HOUR', 3600);
define ('SECONDS_PER_MINUTE', 60);

function niceTTL($seconds) {
	$result = '';
	
	if ($seconds >= SECONDS_PER_DAY) {
		// A day or more
		$result .= intval($seconds/SECONDS_PER_DAY);
		if (intval($seconds/SECONDS_PER_DAY)>=2) {
			$result .= ' days';
		}
		else {
			$result .= ' day';
		}
		$seconds = $seconds - (SECONDS_PER_DAY*intval($seconds/SECONDS_PER_DAY));
	}
	if ($seconds >= SECONDS_PER_HOUR) {
		// An hour or more
		if ($result != '') {
			$result .= ' ';
		}
		$result .= intval($seconds/SECONDS_PER_HOUR) . ' hr';
		$seconds = $seconds - (SECONDS_PER_HOUR*intval($seconds/SECONDS_PER_HOUR));
	}
	if ($seconds >= SECONDS_PER_MINUTE) {
		// A minute or more
		if ($result != '') {
			$result .= ' ';
		}
		$result .= intval($seconds/SECONDS_PER_MINUTE) . ' min';
		$seconds = $seconds - (SECONDS_PER_MINUTE*intval($seconds/SECONDS_PER_MINUTE));
	}
	if ($seconds > 0) {
		// Seconds
		if ($result != '') {
			$result .= ' ';
		}
		$result .= $seconds . ' sec';
	}	
	
	return $result;
}

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
	$fqdn   = $domain;
	echoIfVerbose("Checking $domain \n");
	
	// NOTE: if more than one url= directive uses the [dns] condition, it will really only report
	//   changes for the first url= line that contains 'dns'
	$newURL = !isset($privateStore[$filterName][$domain]['soa']) || (strlen($privateStore[$filterName][$domain]['soa']) == 0);
	echoIfVerbose("New domain? $newURL \n");
	
	///////////////////////////////// 
	// General strategy:
	//   First check SOA
	
	
	try {
		$soa = null;
		$isSOA = checkdnsrr($domain, "SOA");
		echoIfVerbose("SOA? $isSOA \n");
			
//	DNS_A, DNS_CNAME, DNS_HINFO, DNS_MX, DNS_NS, DNS_PTR, DNS_SOA, DNS_TXT, DNS_AAAA, DNS_SRV, 
//  DNS_NAPTR, DNS_A6, DNS_ALL or DNS_ANY
//		$da = dns_get_record($domain, DNS_ALL);
//	print_rIfVerbose($da);
	
		////// SOA
		// Staring with FQDN, whittle down until an SOA
		// can be retrieved.
		$da = false;
		$originalFQDN = $domain;
		while (($i = strpos($domain, '.')) !== false) {
			$da = dns_get_record($domain, DNS_SOA);
			if (count($da)) {
				break;
			}
			$domain = substr($domain, $i+1);
		}
		// At this point $fqdn contains the fully qualified domain name (server name) and
		//   $domain contains the shortened domain name for which we could obtain an SOA.
		if (($da !== false) && isset($da[0])) {
			$da0 = $da[0];
			// Note: don't include ttl because it counts down dynamically - everything else can be used
			$soa = $da0['host']." ".$da0['class']." ".$da0['type']." ".$da0['mname'].". ".$da0['rname']." serial:" .$da0['serial'];
			$soa .= " refresh:".$da0['refresh']    .' ('.niceTTL($da0['refresh'])    .')';
			$soa .= " retry:"  .$da0['retry']      .' ('.niceTTL($da0['retry'])      .')';
			$soa .= " expire:" .$da0['expire']     .' ('.niceTTL($da0['expire'])     .')';
			$soa .= " min-ttl:".$da0['minimum-ttl'].' ('.niceTTL($da0['minimum-ttl']).')';
			echoIfVerbose("$soa \n");
			if (isset($privateStore[$filterName][$domain][DNS_SOA])) {
				if (strcasecmp($soa,$privateStore[$filterName][$domain][DNS_SOA]) != 0) {
					// SOA information has changed
					$result = "Alert";
					$message .=   "SOA changed \n".INDENT."From \"" . $privateStore[$filterName][$domain][DNS_SOA] ."\" \n".INDENT."To \"$soa\"\n";
					echoIfVerbose("SOA changed ".         "from \"" . $privateStore[$filterName][$domain][DNS_SOA] ."\" \n".       "to \"$soa\"\n");	
				}
				else {
					// Not changed - always report back the contents of the SOA
					$message .= "$soa\n";
				}
			}
			else {
					// Have not seen any SOA before
					$result = "Notice";
					$message .=   "SOA first seen \"$soa\"\n";
					echoIfVerbose("SOA first seen \"$soa\"\n");	
			}
			$privateStore[$filterName][$domain][DNS_SOA] = $soa;	
	
			$notify = $args['notify'];
			
			// Save possible error indication because checkEntriesByType() might change it to "OK"
			$previousResult = $result;	
			
			////// MX
			list ($message, $result) = checkEntriesByType($domain, DNS_MX, 'MX', $privateStore, $filterName, $message, 'target', null, $notify);
			if ($result != "OK") {
				$previousResult = $result;
			}		
			////// NS
			list ($message, $result) = checkEntriesByType($domain, DNS_NS, 'NS', $privateStore, $filterName, $message, 'target', null, $notify);
			if ($result != "OK") {
				$previousResult = $result;
			}		
		
			////// TXT
			list ($message, $result) = checkEntriesByType($domain, DNS_TXT, 'TXT', $privateStore, $filterName, $message, 'txt', null, $notify);
			if ($result != "OK") {
				$previousResult = $result;
			}		
		
			////// A     (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_A, 'A', $privateStore, $filterName, $message, array('host', 'ip'), null, $notify);
			if ($result != "OK") {
				$previousResult = $result;
			}		
		
			////// CNAME (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_CNAME, 'CNAME', $privateStore, $filterName, $message, array('host', 'target'), null, $notify);
			if ($result != "OK") {
				$previousResult = $result;
			}		

			////// AAAA  (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_AAAA,  'AAAA',  $privateStore, $filterName, $message, array('host', 'ip'), null, $notify);
			if ($result != "OK") {
				$previousResult = $result;
			}		

			// checkEntriesByType() may have changed the result to OK even if it
			//   previously was something else, so might have to reset it here to
			//   return an error or alert that was previously spotted
			if ($previousResult != "OK") {
				$result = $previousResult;
			}
			
			////// Note that the dns_get_record() function has constants defined for certain types of records.
			//     There may be constants that were not available at the time of this writing.
		}
		else {
			///// When dns_get_record() comes back FALSE, it has failed to retrieve records.
			///// Please note that we check the FQDN, then we progressively strip leading tokens, one dot at
			/////    at a time, and if we get here we have checked every possible domain name right down to
			/////    the TLD and they all failed.
			$result = "Critical";
			$message .= INDENT . "Could not retrieve SOA (start-of-authority) for \"$originalFQDN\"\n";
			echoIfVerbose(       "Could not retrieve SOA (start-of-authority) for \"$originalFQDN\"\n");	
		}
	}
	catch (Exception $dax) {
		$result = "Error";
		$message .= INDENT . "Exception in dns.php::dnsScan() $dax\n";
		echoIfVerbose(       "Exception in dns.php::dnsScan() $dax\n");	
	}

	$message = trim($message , "\n");				// remove any trailing LF
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
function checkEntriesByType($domain, $type, $typeString, &$privateStore, $filterName, $message, $keyField, $keyExtra=null, $notify) {
	// Get all records of a particular type that exist in the domain's DNS
	$da = dns_get_record($domain, $type);
	echoIfVerbose("$typeString count: " . count($da) . "\n");
	$result = "OK";
// vvv
	if (FALSE && isset($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
		unset($privateStore[$filterName][$domain][$typeString.'_POOL']);
	}
	if (FALSE && isset($privateStore[$filterName][$domain][$typeString.'_LAST'])) {
		unset($privateStore[$filterName][$domain][$typeString.'_LAST']);
	}
// nuke all records
	if (FALSE && isset($privateStore[$filterName][$domain][$typeString])) {
		unset($privateStore[$filterName][$domain][$typeString]);
	}
// ^^^
	try {
		if (isset($da) && (count($da) > 0)) {
			// $da contains (array) all of the records of one particular type, 
			//   as reported by our DNS
			if (!isset($privateStore[$filterName][$domain][$typeString])) {
				$result = "Alert";
				$message .= INDENT . "$typeString records are being seen for the first time.\n";
				echoIfVerbose("$typeString records are being seen for the first time.\n");	
			}
			
// vvv
			if (!isset($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
				// No pool yet for this type, so initialize
				$privateStore[$filterName][$domain][$typeString.'_POOL']  = array();
				$privateStore[$filterName][$domain][$typeString.'_LAST'] = array();
			}
// ^^^

			// Build array of current entries (which data depends on parameter $keyField)
			$records = array();
			echoIfVerbose("[dns] '$typeString' records as reported by DNS\n");
			foreach ($da as $dmx) {
				if (isset($dmx['host']) && isset($dmx['ip'])) {
					echoIfVerbose("»»» ".$dmx['host'].' '.$dmx['ip']."\n");
				}
				// $dmx is one single DNS entry
				// It is indexed somewhat like this; may vary.
				// Caller has requested certain ones (in $keyField) and we can
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
				if (!in_array($keyFieldString, $records)) {
					$records[] = $keyFieldString;
				}
			}
			
			// Note any "new" records
			// array_diff() gets us all members of $records that are NOT in the private store from earlier
			if (isset($privateStore[$filterName][$domain][$typeString])) {
				$newRecords = array_diff($records, $privateStore[$filterName][$domain][$typeString]);
				foreach ($newRecords as $dms) {
					// POOLS
					// -> Check pool so we can detect round robin or load sharing
					// This is designed specifically to catch the type of pooling DNS
					//   where only one record (sometimes two) are returned to a DNS 
					//   inquiry but the IP addresses change by rotating through a
					//   pool of IPs. Normally when an IP address changes, we report it
					//   via email as an exception, but in cases where there's a pool, 
					//   we don't want to report these changes since they're normal.
					// (One common place where we see this behavior is from Akamai.)
					if (isset($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
						// Is this server in pool already?
						// Notes:
						// This array contains the records, indexed by record
						//		$privateStore[$filterName][$domain][$typeString.'_POOL']
						// This array contains "last time seen", indexed by full server records
						//		$privateStore[$filterName][$domain][$typeString.'_LAST']
						if (isset($privateStore[$filterName][$domain][$typeString.'_POOL'][$dms])) {
							// Exists in pool, record the "last time seen" 
							//   and (note) do not return error
							echoIfVerbose("Found in pool: $dms time will be updated\n");
							$privateStore[$filterName][$domain][$typeString.'_LAST'][$dms] = time();
						}
						else {
							// Not yet in this pool, add record to array and initial time seen
							$privateStore[$filterName][$domain][$typeString.'_POOL'][$dms] = true;
							$privateStore[$filterName][$domain][$typeString.'_LAST'][$dms] = time();
							// Return a change notification
							$result = "Changed";
							$message .= INDENT . "New $typeString record: $dms \n";
							echoIfVerbose("Was not found in pool\n");
							echoIfVerbose("New $typeString record: $dms\n");
						}
					}
					else {
						// Return a change notification
						$result = "Changed";
						$message .= INDENT . "New $typeString record: $dms \n";
						echoIfVerbose("No pool defined\n");
						echoIfVerbose("New $typeString record: $dms\n");
					}
				}

				// Note any disappeared records
				$goneRecords = array_diff($privateStore[$filterName][$domain][$typeString], $records);
				foreach ($goneRecords as $dms) {
					$result = "Changed";
					$message .= INDENT . "$typeString record deleted: $dms \n";
					echoIfVerbose("$typeString deleted: $dms\n");	
				}
			}
			else {
				// Add to pool - note this is the first time records are being seen,
				//   so initialize the pool.
				$privateStore[$filterName][$domain][$typeString.'_POOL'] = array();
				$privateStore[$filterName][$domain][$typeString.'_LAST'] = array();
				// Add and report each record
				foreach ($records as $dms) {
					// Add this record and set the initial time seen
					$privateStore[$filterName][$domain][$typeString.'_POOL'][$dms] = true;
					$privateStore[$filterName][$domain][$typeString.'_LAST'][$dms] = time();
					// Cause alert
					$result = "Alert";
					$message .= INDENT . "Initial $typeString record: $dms \n";
					echoIfVerbose("Initial $typeString record: $dms\n");	
				}
			}

			// Set the expiration time here
			$expireMinutes = 60*6;			// 6 hours
			$expireSeconds = $expireMinutes*60;
			$expireTime = time() - $expireSeconds;
			echoIfVerbose("Expiration times calculated: $expireSeconds $expireMinutes $expireTime \n");

			// Expire any old pool entries
			if (isset($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
				foreach ($privateStore[$filterName][$domain][$typeString.'_POOL'] as $dms=>$value) {
					$last = $privateStore[$filterName][$domain][$typeString.'_LAST'][$dms];
					if ($last < $expireTime) {
						echoIfVerbose("»»» $dms [$last] has expired and is being deleted from the pool\n");
						unset($privateStore[$filterName][$domain][$typeString.'_POOL'][$dms]);
						unset($privateStore[$filterName][$domain][$typeString.'_LAST'][$dms]);
					}
				}
			}
			
			// During the "notify" hour, if the size of the pool exceeds the
			//   number of current records, then display the pool.
			if (isVerbose() || isNotifyHour($notify)) {
				if (isset($privateStore[$filterName][$domain][$typeString.'_POOL']) && count($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
					if ((count($privateStore[$filterName][$domain][$typeString.'_POOL']) > count($da))) {
						echoIfVerbose("Pool contains ".count($privateStore[$filterName][$domain][$typeString.'_POOL'])." records\n");
						echoIfVerbose("DNS inquiry reported ".count($da)." records\n");
						// If the pool contains more than just the active records
						// This was determined by count, not by comparing records
						$message .= INDENT. "Active pool of '$typeString' records (each expires after $expireMinutes minutes) [< $expireTime]\n";
						echoIfVerbose("[dns] Active pool of '$typeString' records (each expires after $expireMinutes minutes) [< $expireTime]\n");	
						foreach ($privateStore[$filterName][$domain][$typeString.'_POOL'] as $dms=>$value) {
							$last = $privateStore[$filterName][$domain][$typeString.'_LAST'][$dms];
							$message .= INDENT . INDENT . "$dms [$last]\n";
							echoIfVerbose("»»» $dms [$last]\n");
						}
					}
				}
				else {
					echoIfVerbose("No pool (yet) of '$typeString' records\n");
				}
			}
			
			// Save the current server names (using type as spec'd by $typeString)
			$privateStore[$filterName][$domain][$typeString] = $records;
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