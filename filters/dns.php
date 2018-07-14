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

/**
		NOTE: if more than one url= directive in a single properties file uses the [dns] 
	 	filter to observe DNS on the same FQDN it may not report accurately the changes
		for both URLs. So it is best to only request the [dns] filter once for each FQDN
		in a properties file.
		Two different URLs on the same FQDN one after the other in one properties file
		may report differening SOA or other DNS records because of rotating or differing 
		DNS responses for that domain. So beware.
		This isn't necessarily because WE are doing anything wrong. It's more likely the
		DNS is reporting inconsistently over time or over multiple servers. We tolerate
		this, but we do trigger alerts due to the changes. Better to alert than to miss
		something important.
		 	For example, consider a load-balanced DNS with several NS servers, being 
		 	updated by its owner. If server 'a' receives the update, but server 'b' is  
		 	delayed in receiving it, two consecutive DNS inquiries from us might 
		 	retrieve different results, which we would report as a "change" (and we'd  
			send an alert). This can confuse everyone. Regardless, it is just "The 
			Internet being the Internet."
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
	

		// Set up pool expiration time (default is 24 hours - set in cyberspark.sysdefs.php)
		$expireMinutes = DEFAULT_DNS_POOL_EXPIRE_MINUTES;
		if (isset($args['dnsexpire'])) {
			$expireMinutes = $args['dnsexpire'];
		}

		// Save possible error indication because checkEntriesByType() might change it to "OK"
		$previousResult = $result;	
			
		////// SOA
		// Staring with FQDN, attempt to retrieve SOA. If one isn't available, then strip the
		// leading token up to the first dot and try again. Continue whittling down the
		// FQDN until either you retrieve an SOA or have no dots remaining. If you end up
		// with no SOA at all, report it. Most FQDN will resolve once you reach the form 
		// X.TLD (i.e. name dot top-level-domain) - meaning that for example 
		// www.cyberspark.net would not have an SOA but stripping the 'www.' and trying
		// cyberspark.net would yield an SOA.
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
			//
			// ALSO: If Cloudflare, then the SOA 'serial' often changes without any other visible change,
			//   so for Cloudflare, do not compare 'serial'. Instead insert a (constant) comment
			//   indicating it's being ignored.
			if (stripos($da0['mname'], 'cloudflare')!==FALSE) {
				// Cloudflare
				$soa = $da0['host'].' '.$da0['class'].' '.$da0['type'].' '.$da0['mname'].'. '.$da0['rname'].' (Cloudflare serial ignored)';
			}
			else {
				// Non-cloudflare
				$soa = $da0['host'].' '.$da0['class'].' '.$da0['type'].' '.$da0['mname'].'. '.$da0['rname'].' serial:' .$da0['serial'];
			}
			$soa .= " refresh:".$da0['refresh']    .' ('.niceTTL($da0['refresh'])    .')';
			$soa .= " retry:"  .$da0['retry']      .' ('.niceTTL($da0['retry'])      .')';
			$soa .= " expire:" .$da0['expire']     .' ('.niceTTL($da0['expire'])     .')';
			$soa .= " min-ttl:".$da0['minimum-ttl'].' ('.niceTTL($da0['minimum-ttl']).')';
			echoIfVerbose("$soa \n");
			if (isset($privateStore[$filterName][$domain]['SOA'])) {
				if ($k=strcasecmp($soa, $privateStore[$filterName][$domain]['SOA'])) {
					// SOA information has changed
// vvv
//	This code alerts any time SOA changes. Worked fine until mid-2017 when some SOA records
//		started changing with no apparent reason. Also some SOAs seem to be
//		round-robin with just 2 or 3 values.
//		If you activate these lines, you'll be alerted on every change.
//					$result = "Alert";
//					$message .=   "SOA changed \n".INDENT."From \t\"" . $privateStore[$filterName][$domain]['SOA'] ."\" \n".INDENT."To \t\"$soa\"\n";
//					echoIfVerbose("SOA changed ".         "from \""   . $privateStore[$filterName][$domain]['SOA'] ."\" \n".       "to   \"$soa\"\n");	
// ^^^
// vvv
// PROPOSED CHANGE 2017-11-10
// 			(You'll want to test when you know there's an SOA available that is pooled. 
// 			 As of today I don't see one, but I've seen them in the past.)
//		If you activate these lines you'll treat SOA as possibly pooled.
					list ($message, $result) = checkEntriesByType($domain, DNS_SOA, 'SOA', $privateStore, $filterName, $message, 'target', null, $notify, $expireMinutes);
					if ($result != "OK") {
						$previousResult = $result;
					}		
// ^^^
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
			$privateStore[$filterName][$domain]['SOA'] = $soa;	
	
			$notify = $args['notify'];
			
			////// MX
			list ($message, $result) = checkEntriesByType($domain, DNS_MX, 'MX', $privateStore, $filterName, $message, 'target', null, $notify, $expireMinutes);
			if ($result != "OK") {
				$previousResult = $result;
			}		
			////// NS
			list ($message, $result) = checkEntriesByType($domain, DNS_NS, 'NS', $privateStore, $filterName, $message, 'target', null, $notify, $expireMinutes);
			if ($result != "OK") {
				$previousResult = $result;
			}		
		
			////// TXT
			list ($message, $result) = checkEntriesByType($domain, DNS_TXT, 'TXT', $privateStore, $filterName, $message, 'txt', null, $notify, $expireMinutes);
			if ($result != "OK") {
				$previousResult = $result;
			}		
		
			////// A     (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_A, 'A', $privateStore, $filterName, $message, array('host', 'ip'), null, $notify, $expireMinutes);
			if ($result != "OK") {
				$previousResult = $result;
			}		
		
			////// CNAME (use fqdn)
			// (My recollection is that CNAME doesn't always get the right information - i.e. it is "unreliable") -Sky
			list ($message, $result) = checkEntriesByType($fqdn, DNS_CNAME, 'CNAME', $privateStore, $filterName, $message, array('host', 'target'), null, $notify, $expireMinutes);
			if ($result != "OK") {
				$previousResult = $result;
			}		

			////// AAAA  (use fqdn)
			list ($message, $result) = checkEntriesByType($fqdn, DNS_AAAA,  'AAAA',  $privateStore, $filterName, $message, array('host', 'ip'), null, $notify, $expireMinutes);
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
function checkEntriesByType($domain, $type, $typeString, &$privateStore, $filterName, $message, $keyField, $keyExtra=null, $notify, $expireMinutes=1440) {
	// Get all records of a particular type that exist in the domain's DNS
	$da = dns_get_record($domain, $type);
	echoIfVerbose("$typeString count: " . count($da) . "\n");
	$nowTime = time();
	$showPool = false;
	$result = "OK";
	$exmsg = niceTTL($expireMinutes*60);
// vvv
	// For debugging purposes
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
			
			if (!isset($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
				// No pool yet for this type, so initialize
				$privateStore[$filterName][$domain][$typeString.'_POOL']  = array();
				$privateStore[$filterName][$domain][$typeString.'_LAST'] = array();
			}

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
							echoIfVerbose("Found in pool: $dms time will be updated to $nowTime\n");
							$privateStore[$filterName][$domain][$typeString.'_LAST'][$dms] = $nowTime;
						}
						else {
							// Not yet in this pool, add record to array and initial time seen
							$privateStore[$filterName][$domain][$typeString.'_POOL'][$dms] = true;
							$privateStore[$filterName][$domain][$typeString.'_LAST'][$dms] = $nowTime;
							// Return a change notification
							$result = "Changed";
							$message .= INDENT . "New $typeString record: $dms \n";
							echoIfVerbose("New $typeString record: $dms\n");
							echoIfVerbose("Was not found in pool\n");
							$showPool = true;	// show pool upon completion
						}
					}
					else {
						// Return a change notification
						$result = "Changed";
						$message .= INDENT . "New $typeString record: $dms \n";
						echoIfVerbose("No pool defined - (this is probably a bug in dns.php)\n");
						echoIfVerbose("New $typeString record: $dms\n");
					}
				}

				// Alert if any disappeared records
				if (count($privateStore[$filterName][$domain][$typeString.'_POOL']) <= count($da)) {
					// Pool and live had the same number of records, or pool was smaller, 
					//   so notify that records are being dropped from live DNS.
					// In cases where the pool was larger, the dropping records will all
					//   be retained in the pool for a while, so if they reappear (because
					//   of DNS pooling) we won't be notifying of either the drop or the
					//   reappearance.
					$goneRecords = array_diff($privateStore[$filterName][$domain][$typeString], $records);
					foreach ($goneRecords as $dms) {
						$result = "Changed";
						$message .= INDENT . "$typeString record deleted: $dms \n";
						echoIfVerbose("$typeString deleted: $dms\n");	
					}
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
					$privateStore[$filterName][$domain][$typeString.'_LAST'][$dms] = $nowTime;
					// Cause alert
					$result = "Alert";
					$message .= INDENT . "Initial $typeString record: $dms \n";
					echoIfVerbose("Initial $typeString record: $dms\n");	
				}
			}

			// Set the expiration time here
			$expireSeconds = $expireMinutes*60;
			$expireTime = $nowTime - $expireSeconds;
			echoIfVerbose("Expiration times calculated: $expireSeconds $expireMinutes $expireTime (now is $nowTime)\n");

			// Expire any "old" pool entries
			if (isset($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
				foreach ($privateStore[$filterName][$domain][$typeString.'_POOL'] as $dms=>$value) {
					$last = $privateStore[$filterName][$domain][$typeString.'_LAST'][$dms];
					if ($last < $expireTime) {
						$result = "Alert";
						$showPool = true;
						// A record is added to our internal pool whenever it first appears in DNS. It then expires from our pool after
						//   a time (default is once a day) IF it has not been seen again during that time. (If it is seen,
						//   a new _LAST time is entered and it will not expire.) It would expire only if a round-robin or
						//   pooling scheme used a particular record less than every day. I think this is unlikely to be seen in real life.
						// The most common case is that a DNS presents a single record of one type repeatedly without varying (i.e. NOT a
						//   pooled service), in which case we initially insert it in our pool, then repeatedly get the same value from DNS, 
						//   updating _LAST each time, so it never expires. If the DNS presents a replacement record at some point, then 
						//   our pooled value will expire a day later (or as spec'd above) and generate this message, which is no big deal.
						//   (Note that you'd receive an immediate notification of the "new" record, but the notification of the expiration
						//   of the old record is sent a day later at the time it expires.)
						// Also we pay no attention to the "expires" that may be present in the SOA - just sayin'.
						// For example, when an "A" record changes, we end up having two in the pool (the old one and the new one) for 
						//   a day, then after a day has passed without the old one appearing in DNS, it expires and triggers this message.
						$message .= INDENT . "\"$typeString\" record ($dms) hasn't been seen in more than $exmsg and was deleted\n";
						if (count($privateStore[$filterName][$domain][$typeString.'_POOL']) > 2) {
							// Add a notation that this may be pooled DNS - this goes out if there are more than two records in our
							//   internal pool. For example, say there's a single "A" record 
							$message .= INDENT . INDENT . "from what might be a pooled, round-robin, or load-sharing DNS\n";
						}
						echoIfVerbose("»»» \"$typeString\" record ($dms) hasn't been seen in more than $exmsg and was deleted\n");
						unset($privateStore[$filterName][$domain][$typeString.'_POOL'][$dms]);
						unset($privateStore[$filterName][$domain][$typeString.'_LAST'][$dms]);
					}
				}
			}
			
			// During the "notify" hour, if the size of the pool exceeds the
			//   number of current records, then display the pool.
			if ($showPool || isVerbose() || isNotifyHour($notify)) {
				if (isset($privateStore[$filterName][$domain][$typeString.'_POOL']) && count($privateStore[$filterName][$domain][$typeString.'_POOL'])) {
					if (count($privateStore[$filterName][$domain][$typeString.'_POOL']) > count($da)) {
						echoIfVerbose("Pool contains ".count($privateStore[$filterName][$domain][$typeString.'_POOL'])." records\n");
						echoIfVerbose("DNS inquiry reported ".count($da)." records\n");
						// If the pool contains more than just the active records
						// This was determined by count, not by comparing records
						$message .= INDENT. "Active pool of '$typeString' records (each expires after $exmsg)\n";
						echoIfVerbose("[dns] Active pool of '$typeString' records (each expires after $exmsg)\n");	
						foreach ($privateStore[$filterName][$domain][$typeString.'_POOL'] as $dms=>$value) {
							$last = $privateStore[$filterName][$domain][$typeString.'_LAST'][$dms];
							$tR   = $last - ($nowTime - $expireSeconds);
							$timeRemainingMessage = niceTTL($tR) . ' remaining';
							$expiresSoonMessage   = '';
							if ($tR < 60*60*2) {
								// Little time remaining for this record in pool
								$expiresSoonMessage = " || expires soon!";
							}
							$tSS = $nowTime - $last;
							$timeSeenMessage = '';
							$timeSinceSeen = niceTTL($tSS);
							if ($tSS < 1) {
								$timeSeenMessage = "new || ";
							}
							else {
								$timeSeenMessage = "last seen $timeSinceSeen ago || ";
							}
							$message .= INDENT . INDENT . "$dms ($timeSeenMessage$timeRemainingMessage$expiresSoonMessage)\n";
							echoIfVerbose("»»» $dms ($timeSeenMessage$timeRemainingMessage$expiresSoonMessage)\n");
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