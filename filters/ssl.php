<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: ssl  (SSL)
		Checks SSL certificate (for HTTPS).
		- First time one is seen, it gets recorded as a BASELINE.
		- Next time it's seen, it is checked and you are warned if it changed.
		- Any SSL/HTTPS connection error or a failure of the cert to validate is reported.
		Depends on cURL, and PHP must be version 5.3.2 or later
			CURLOPT_SSL_VERIFYPEER
			CURLOPT_SSL_VERIFYHOST
	*/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";
include_once "include/functions.inc";

///////////////////////////////// 
function extractCertificates($string) {
	$result = '';
	$beginString = '-----BEGIN CERTIFICATE-----';
	$endString   = '-----END CERTIFICATE-----';
	$i = strpos($string, $beginString, 0);
	while (($i !== false) && ($i >= 0)) {
		$j = strpos($string, $endString, $i);
		if (($j !== false) && ($j >= 0) && ($j > $i)) {
			// Copy out a cert
			$result .= substr($string, $i, ($j+strlen($endString)-$i)) . "\n";
			$i = strpos($string, $beginString, $j);	// look for next cert
		}
		else {
			break;
		}
	}
	return $result;		
}

///////////////////////////////// 
function sslScan($content, $args, $privateStore) {
	$filterName = "ssl";
	$result   = "OK";						// default result
	$url = $args['url'];
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

	echoIfVerbose("The [SSL] filter was invoked \n");
	$domain = domainOnly($url);
	$fqdn   = fqdnOnly($url);
	echoIfVerbose("Checking $domain \n");
	
	// NOTE: if more than one url= directive uses the [dns] condition, it will really only report
	//   changes for the first url= line that contains 'dns'
	$newURL = !isset($privateStore[$filterName][$domain]['soa']) || (strlen($privateStore[$filterName][$domain]['soa']) == 0);
	echoIfVerbose("New domain? $newURL \n");
	
	///////////////////////////////// 
	// General strategy:
	//   Try to get the cert
	// http://stackoverflow.com/questions/3081042/how-to-get-ssl-certificate-info-with-curl-in-php	
	
	$result = "OK";
	$message = "Verify SSL/HTTPS on $fqdn \n";

	try {
		$stderrString='';
		//// Get the cert information using cURL (requires PHP 5.3.2 or later)
		try {
			// Requires PHP 5.3.x
			//$g = stream_context_create (array("ssl" => array("capture_peer_cert" => true)));
			//$r = stream_socket_client("ssl://www.google.com:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $g);
			//$cont = stream_context_get_params($r);
			//print_r($cont);
			if ($tf = tmpfile()) { 
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://'.$fqdn);
				curl_setopt($ch, CURLOPT_STDERR,     $tf);
				curl_setopt($ch, CURLOPT_CERTINFO,   1);
				curl_setopt($ch, CURLOPT_VERBOSE,    true);
				curl_setopt($ch, CURLOPT_HEADER,     false);
				curl_setopt($ch, CURLOPT_NOBODY,     1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_SSLVERSION,     3);
				$curlResult = curl_exec($ch);
				curl_errno($ch);
				fseek($tf, 0); // rewind
				while(strlen($stderrString.=fread($tf,8192))==8192);
				fclose($tf);
				curl_close($ch);
			}
		}
		catch (Exception $certx) {
		}
		//// If got the cert information, look for certain telltales
		if (stripos($stderrString,'failed')>0 || stripos($stderrString,'problem')>0) {
			// cURL is reporting a problem directly - return everything it said.
			// This does NOT include any cert, so we don't update our store.
			$result = "Critical";
			$message .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
			$message .= INDENT.$stderrString."\n";
		}
		else if (stripos($stderrString,'SSL certificate verify ok')>0) {
			// The cert is valid.
			$result = "OK";
			// Check the cert(s) that were presented to us against the BASELINE cert in our store
			$certs = extractCertificates($stderrString);
			if (isset($privateStore[$filterName][$domain][SSL_BASELINE_CERT])) {
				// Compare the cert(s) against what we have in our store
				if (strcmp($certs, $privateStore[$filterName][$domain][SSL_BASELINE_CERT]) != 0) {
					// This cert does not match the BASELINE cert!
					$result = "Critical";
					$message .= INDENT."The SSL certificate presented by the server doesn't match the previous cert! This is either a serious error or they've update the cert. Check it carefully!\n\n";
					$message .= INDENT."INTERACTION:\n" .$stderrString."\n\n";
					$message .= INDENT."PREVIOUS CERT(S):\n" .$privateStore[$filterName][$domain][SSL_BASELINE_CERT]."\n\n";
				}
			}
			else {
				// First time we've seen this server, so record a BASELINE version of the cert
				$result = "Critical";
				$message .= INDENT."First time we've seen this certificate. Examine the interaction carefully. Things may be just fine. Here is a transcript of the interaction with the HTTPS server.\n\n";
				$message .= INDENT."INTERACTION:\n" .$stderrString."\n\n";
				$message .= INDENT."SAVED NEW BASELINE CERT(S) AS FOLLOWS:\n$certs\n\n";
			}
			// Save just the cert(s) - note that if the BASELINE differed above, the new cert replaces the former baseline
			$privateStore[$filterName][$domain][SSL_BASELINE_CERT] = $certs;
		}
		else if (strcasecmp($privateStore[$filterName][$domain][SSL_VERBOSE_RESULT], $stderrString) != 0) {
			// The "verbose" result returned by the CURLOPT_SSL_VERIFY option is neither OK nor failed
			//   so report out what it contains.
			$result = "Critical";
			$message .= INDENT."Something is odd here. The certificate is neither 'valid' nor 'failed' - - Examine the details carefully under 'CURRENT' below. These are transcripts of the interactions with the HTTPS server.\n\n";
			$message .= INDENT."PREVIOUS:\n".$privateStore[$filterName][$domain][SSL_VERBOSE_RESULT]."\n\n";
			$message .= INDENT."CURRENT:\n" .$stderrString."\n\n";
		}
		$privateStore[$filterName][$domain][SSL_VERBOSE_RESULT] = $stderrString;
	}
	catch (Exception $dax) {
		$result = "ssl";
		$message .= INDENT . "Exception in filters:SSL:SSLScan() $dax \n";
		echoIfVerbose("Exception in fliters:SSL:SSLScan() $dax\n");	
	}


	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function sslInit($content, $args, $privateStore) {
	$filterName = "ssl";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$result   = "OK";						// default result
	$message = "[filterName] Scanning " . $args['url'];

	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function sslDestroy($content, $args, $privateStore) {
	$filterName = "ssl";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$result   = "OK";						// default result
	$message = "[$filterName] Shut down.";
	return array($message, $result, $privateStore);
	
}

///////////////////////////////// 
function SSL($args) {
	$filterName = "ssl";
 	if (!registerFilterHook($filterName, 'scan', 'sslScan', 40)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', 'sslInit', 40)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', 'sslDestroy', 40)) {
		echo "The filter '$filterName' was unable to add a 'Destroy' hook. \n";	
		return false;
	}
	return true;
}

?>