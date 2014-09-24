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

	/**
		SPECIAL NOTE for this 'ssl' filter:
		Must have CA certificates installed in order to check SSL cert validity.
		Use command line:
		  aptitude install ca-certificates
		when setting up CyberSpark monitoring.
	**/
	
	/**
		See the file 'how_to_make_a_plugin_filter.php' for more instructions
		on how to make one of these and integrate it into the CyberSpark daemons.
	**/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

include_once "include/echolog.inc";
include_once "include/functions.inc";

/////////////////////////////////////////////////////////////////////////////////
// If you set SSL_FILTER_REQUIRE_EXPLICIT_OK to true, the 'ssl' filter looks for a definitive
//   "OK" result and if it's not there, you'll get a report saying there's a problem.
// If you set SSL_FILTER_REQUIRE_EXPLICIT_OK to false, then the filter only considers certain
//   explicit error conditions, and may let some unanticipated errors slip through.
//   As long as the checker returns "OK" status in its messages, we consider the
//   cert to be OK.
// (Recommended default setting is TRUE.)
if (!defined('SSL_FILTER_REQUIRE_EXPLICIT_OK')) {
	define ('SSL_FILTER_REQUIRE_EXPLICIT_OK', true);
}

define ('SSL_FILTER_BUFFER_SIZE', 100000);	// buffer size for reading SSL results (over this is discarded)
define ('SSL_FILTER_EXCERPT_BACKSPACE', 50);
define ('SSL_FILTER_EXCERPT_LENGTH'   , 150);

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
		//   Check for version
		$cv = phpversion();
		if (version_compare($cv, '5.3.2') < 0) {
			$result = "Critical";
			$message .= INDENT."The 'ssl' filter requires PHP version 5.3.2 or later on the CyberSpark server.\n";
			$message .= INDENT."The version being used is $cv\n";
			$message .= INDENT."This is a CyberSpark configuration error, NOT an error on the monitored site or URL.\n";
			$message .= INDENT.$stderrString."\n";
		}
		else {
			try {
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
					// Reminder you can do this if you need specific additional CA certs
					// curl_setopt ($ch, CURLOPT_CAINFO, "pathto/cacert.pem");
					//
					$curlResult = curl_exec($ch);
					curl_errno($ch);
					fseek($tf, 0); // rewind
					// Get the full analysis that was returned from the SSL connection
					// Note there is an absolute maximum size used, and although it's very high,
					//   there is a chance the report could be truncated. Mostly what this 
					//   would mean is you'd have no "OK" final result found in the result.
					$s = fread($tf, SSL_FILTER_BUFFER_SIZE);
					if ($s !== false) {
						$stderrString .= $s;
					}
					fclose($tf);
					curl_close($ch);
				}
			}
			catch (Exception $certx) {
				$result = "Critical";
				$message .= INDENT."The 'ssh' filter got snarled trying to use cURL. Exception:'".$certx->getMessage()."\n";
				$message .= INDENT.$stderrString."\n";
			}
		}
		//// If got the cert information, look for certain telltales
		$iFailed = stripos($stderrString,'failed');
		$iProblem = stripos($stderrString,'problem');
		if ($iFailed>0 || $iProblem>0) {
			// cURL is reporting a problem directly - return everything it said.
			// This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$message .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
			if ($iFailed !== false) {
				$iFailed = ($iFailed>SSL_FILTER_EXCERPT_BACKSPACE)?($iFailed-SSL_FILTER_EXCERPT_BACKSPACE):0;
				$message .= INDENT.INDENT."The word 'Failed' appears near '".substr($stderrString, $iFailed, SSL_FILTER_EXCERPT_LENGTH)."'\n";
			}
			if ($iProblem !== false) {
				$iProblem = ($iProblem>SSL_FILTER_EXCERPT_BACKSPACE)?($iProblem-SSL_FILTER_EXCERPT_BACKSPACE):0;
				$message .= INDENT.INDENT."The word 'Problem' appears near '".substr($stderrString, $iProblem, SSL_FILTER_EXCERPT_LENGTH)."'\n";
			}
			$message .= INDENT.$stderrString."\n";
		}
		else if (stripos($stderrString,'subjectaltname does not match')>0) {
			// cURL is reporting a problem directly - return everything it said.
			// This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$message .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
			$message .= INDENT."The name in the certificate does not match the site domain.\n";
			$message .= INDENT.$stderrString."\n";
		}
		else if (stripos($stderrString,'SSL peer certificate or SSH remote key was not OK')>0) {
			// cURL is reporting a problem directly - return everything it said.
			// This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$message .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
			$message .= INDENT."The difficulty might be with the root CA signature.\n";
			$message .= INDENT.$stderrString."\n";
		}
		else if (stripos($stderrString,'SSL connection timeout')>0) {
			// cURL timed out when attempting to connect.
			// This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$message .= INDENT."The HTTPS connection timed out, so there is no new (current) certificate info.\n";
			$message .= INDENT."The previous cert information will be retained for comparison during the next attempt.\n";
			$message .= INDENT.$stderrString."\n";
		}
		else if ((!SSL_FILTER_REQUIRE_EXPLICIT_OK) || (stripos($stderrString,'SSL certificate verify ok')>0)) {
			// The cert is valid.
			$result = "OK";
			// Check the cert(s) that were presented to us against the BASELINE cert we have from last time
			$certs = extractCertificates($stderrString);
			if (isset($privateStore[$filterName][$domain]['SSL_BASELINE_CERT'])) {
				// Compare the cert(s) against what we have in our store
				if (strcmp($certs, $privateStore[$filterName][$domain]['SSL_BASELINE_CERT']) != 0) {
					// This cert does not match the BASELINE cert!
					$result = "Critical";
					$message .= INDENT."The SSL certificate presented by the server is valid but doesn't match the previous cert! This is either a serious error or they've updated the cert. Check it carefully!\n\n";
					$message .= INDENT."INTERACTION:\n" .$stderrString."\n\n";
					$message .= INDENT."PREVIOUS CERT(S):\n" .$privateStore[$filterName][$domain]['SSL_BASELINE_CERT']."\n\n";
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
			$privateStore[$filterName][$domain]['SSL_BASELINE_CERT'] = $certs;
		}
		else if (strcasecmp($privateStore[$filterName][$domain]['SSL_VERBOSE_RESULT'], $stderrString) != 0) {
			// The "verbose" result returned by the CURLOPT_SSL_VERIFY option is neither OK nor failed
			//   so report out what it contains.
			// This is a kind of ambiguous situation and many times we see this on a
			// cert that verifies just fine using other methods.
			$result = "Critical";
			$message .= INDENT."Something is odd here. The certificate is neither 'valid' nor 'failed' - - Examine the details carefully under 'CURRENT' below. These are transcripts of the interactions with the HTTPS server.\n\n";
			$message .= INDENT."PREVIOUS:\n".$privateStore[$filterName][$domain]['SSL_VERBOSE_RESULT']."\n\n";
			$message .= INDENT."CURRENT:\n" .$stderrString."\n\n";
		}
		$privateStore[$filterName][$domain]['SSL_VERBOSE_RESULT'] = $stderrString;
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
	$message = "[$filterName] Initialized. URL is " . $args['url'];

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

?>