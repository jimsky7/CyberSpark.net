<?php
	/**
		CyberSpark.net monitoring-alerting system
		FILTER: ssl  (SSL)
		Checks SSL certificate (for HTTPS).
		- First time one is seen, it gets recorded as a BASELINE.
		- Next time it's seen, it is checked and you are warned if it changed.
		- Any SSL/HTTPS connection error or a failure of the cert to validate is reported.
		Depends on cURL (+libcurl), and PHP must be version 5.3.2 or later
			CURLOPT_SSL_VERIFYPEER
			CURLOPT_SSL_VERIFYHOST
		Thanks to Daniel Stenberg http://curl.haxx.se/ for the libcurl library and
		    his suggestions on how to better utilize php-curl. See notes inline.
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
global $path;
include_once $path."cyberspark.config.php";

include_once $path."include/echolog.php";
include_once $path."include/functions.php";

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

define ('CURL_ERR_BUFFER_SIZE', 2048);		// libcurl error buffer length
// Note: We depend on libcurl message stream (below) for a running "analysis"
//   of what the libcurl is seeing when processing HTTP. Those messages are limited
//   to 2048 in length, which means when they include a certificate it will be
//   truncated to 2048 characters, which may lead you to think there's a bug. So
//   we removed these from the "stderr" it returns. If libcurl were to change its
//   "err" message length, you'd have to change this constant here. See http://curl.haxx.se/

define ('BEGIN_CERTIFICATE', '-----BEGIN CERTIFICATE-----');
define ('END_CERTIFICATE',   '-----END CERTIFICATE-----'  );

///////////////////////////////// 
function removeCerts($analysis) {
	$result = $analysis;

	// Remove any certs from the analysis string
	$i = strpos($result, BEGIN_CERTIFICATE);
	while ($i !== false) {
		$s2048 = substr($result, $i, CURL_ERR_BUFFER_SIZE);
		$j = strpos($s2048, END_CERTIFICATE);
		if ($j !== false) {
			// There's an -----END CERTIFICATE-----
			// Remove any slack between it and the next "*" 
			//   (libcurl may leave junk there -- it's a bug)
			$tail2048 = substr($result, ($i+$j+25));
			$k = strpos($tail2048, '*');
			if ($k !== false) {
				$tail2048 = substr($tail2048, $k);
			}
			$result = substr($result, 0, $i) . "\n{CERTIFICATE removed here}\n" . $tail2048;
		}
		else {
			if (strlen($s2048) == CURL_ERR_BUFFER_SIZE) {
				// Remove 2048 characters exactly because there's no END
				$result = substr($result, 0, $i) . "\n{CERTIFICATE removed here}\n" . substr($result, ($i + CURL_ERR_BUFFER_SIZE));
			}
			else {
				// Remove less than 2048 because we're at end of string
				$result = substr($result, 0, $i) . "\n{CERTIFICATE removed here}\n";
			}
		}
		$i = strpos($result, BEGIN_CERTIFICATE);
	}
	return $result;
}

///////////////////////////////// 
function makeAnalysisMessage($analysisMessage, $certs, $analysis, $stderrString) {
	$result = $analysisMessage;

	$result .= INDENT."CERTIFICATES >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>\n";
	$result .= INDENT.INDENT."(SSL certificates have been logged, but are not included in this analysis)\n";
//	$result .= $certs."\n\n";		// human doesn't need the actual certs every time

	$result .= INDENT."ANALYSIS from libcurl >>>>>>>>>>>>>>>>>>>>>\n";
	$result .= removeCerts($analysis)."\n\n";

	$result .= INDENT."INTERACTION >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>\n";
	$result .= removeCerts($stderrString)."\n\n";

	$result .= INDENT."END OF SSL ANALYSIS >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>\n";

	return $result;
}


///////////////////////////////// 
function sslScan($content, $args, $privateStore) {
	$filterName = "ssl";
	$result   = "OK";						// default result
	$url = $args['url'];
	if (isset($args['useragent'])) {
		$userAgent = $args['useragent'];
	}
	
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $store is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "";

	echoIfVerbose("The [SSL] filter was invoked \n");
	$domain = domainOnly($url);
	$fqdn   = $domain;
	echoIfVerbose("Checking $domain \n");
	
	// NOTE: if more than one url= directive uses the [dns] condition, it will really only report
	//   changes for the first url= line that contains 'dns'
	$newURL = !isset($privateStore[$filterName][$domain]['soa']) || (strlen($privateStore[$filterName][$domain]['soa']) == 0);
	echoIfVerbose("New domain? $newURL \n");
	
	///////////////////////////////// 
	// General strategy:
	//   Try to get the cert. Note that the URL has already been spidered, but we do a
	//   separate HTTP GET here using php-curl in order to get a lot more informaiton.
	//   Other filters will use the "other" copy of the GET and any info it provided.
	
	if (!function_exists('curl_init')) {
		$result = "Critical";
		$message = "php-curl is required by the SSL filter, but has not been installed on this server.";
		return array($message, $result, $privateStore);
	}
	
	$result = "OK";
	$versionInfo = curl_version();
	$message = '';				// message to be returned
	$analysisMessage = '';		// detailed analysis messages from libCurl
	$needErrString = false;
	$certs      = '';
	$analysis   = '';

	$message .= "Verifying SSL/HTTPS on $fqdn using php-curl (libcurl '$versionInfo[version]') with OpenSSL '$versionInfo[ssl_version]'\n";
		
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
			$message .= "\n".$stderrString."\n";
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
//					curl_setopt($ch, CURLOPT_SSLVERSION,     3);
					if (isset($userAgent) && strlen($userAgent)) {
						curl_setopt($ch, CURLOPT_USERAGENT,		$userAgent);
					}
					// Reminder you can do this if you need specific additional CA certs
					// curl_setopt ($ch, CURLOPT_CAINFO, "pathto/cacert.pem");
					//
					
					$curlResult = curl_exec($ch);
					
					curl_errno($ch);
					// Get and save the cert info from all SSL certs that were presented
					$ci = curl_getinfo($ch);
					foreach ($ci['certinfo'] as $certinfo) {
						ob_start();
							print_r($certinfo);
						$analysis .= ob_get_clean();
						// Save the actual certs
						$certs    .= $certinfo['Cert'];
					}
					
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
				$message .= INDENT."INTERACTION:\n" .$stderrString."\n\n";
				$needErrString = false;
			}
		}
		//// If got the cert information, look for certain telltales.
		//   If errors, insert notification here.
		$reported = false;
		foreach (array('failure', 'failed', 'problem') as $failureType) {
			$i = stripos($stderrString,$failureType);
			if ($i !== false) {
				if (!$reported) {
					// cURL is reporting a problem directly - return everything it said.
					// This does NOT include any cert, so we don't update the store.
					$result = "Critical";
					$message .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
					$reported = true;
				}
				$i = ($i>SSL_FILTER_EXCERPT_BACKSPACE)?($i-SSL_FILTER_EXCERPT_BACKSPACE):0;
				$message .= INDENT.INDENT."The word '$failureType' appears near '".substr($stderrString, $i, SSL_FILTER_EXCERPT_LENGTH)."'\n";
			}
		}

		//// Insert other information...
		//		Note that the analysis message will be sent by email, so certs are removed because
		//		there's no way a human wants to see them and they make the message challenging to
		//		read. They are nevertheless logged locally, so you could get them if you want them.
//		$analysisMessage = makeAnalysisMessage($analysisMessage, $certs, $analysis, $stderrString);

		$needErrString = false;
		if (stripos($stderrString,'subjectaltname does not match')>0) {
			//// cURL is reporting a problem directly - return everything it said.
			//   This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$analysisMessage .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
			$analysisMessage .= INDENT."The name in the certificate does not match the site domain.\n\n";
			$needErrString = true;
		}
		else if (stripos($stderrString,'SSL peer certificate or SSH remote key was not OK')>0) {
			// cURL is reporting a problem directly - return everything it said.
			// This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$analysisMessage .= INDENT."There is a critical problem with the SSL certificate (HTTPS) for this site!\n";
			$analysisMessage .= INDENT."The difficulty might be with the root CA signature.\n\n";
			$needErrString = true;
		}
		else if (stripos($stderrString,'SSL connection timeout')>0) {
			// cURL timed out when attempting to connect.
			// This does NOT include any cert, so we don't update the store.
			$result = "Critical";
			$analysisMessage .= INDENT."The HTTPS connection timed out, so there is no new (current) certificate info.\n";
			$analysisMessage .= INDENT."The previous cert information will be retained for comparison during the next attempt.\n\n";
			$needErrString = true;
		}
		else if ((!SSL_FILTER_REQUIRE_EXPLICIT_OK) || (stripos($stderrString,'SSL certificate verify ok')>0)) {
			// The cert is valid.
			$result = "OK";
			// Check the cert(s) that were presented to us against the BASELINE cert we have from last time
			if (isset($privateStore[$filterName][$domain]['SSL_BASELINE_CERT'])) {
				// Compare the cert(s) against what we have in our store
				if (strcmp($certs, $privateStore[$filterName][$domain]['SSL_BASELINE_CERT']) != 0) {
					// This cert does not match the BASELINE cert!
					$result = "Critical";
					$analysisMessage .= INDENT."The SSL certificate presented by the server is valid but doesn't match the previous cert! \n";
					$analysisMessage .= INDENT."This is either a serious error or the cert was recently updated. Check it carefully!\n\n";
//	AS OF   2018-07-07
					$analysisMessage .= INDENT."You can check certs at https://www.sslshopper.com/certificate-decoder.html\n\n";
//	DEFUNCT 2018-07-07
//					$analysisMessage .= INDENT."You can check the certs at https://www.sslchecker.com/certdecoder\n\n";
					$analysisMessage .= INDENT."CURRENT (NEW) CERT(S):\n" .$certs."\n\n";
					$analysisMessage .= INDENT."PREVIOUS CERT(S):\n" .$privateStore[$filterName][$domain]['SSL_BASELINE_CERT']."\n\n";
					$needErrString = true;
				}
			}
			else {
				// First time we've seen this server, so record a BASELINE version of the cert
				$result = "Critical";
				$analysisMessage .= INDENT."This is a certificate seen for the first time. Examine the interaction carefully. Things may be just fine. \n";
//	AS OF   2018-07-07
				$analysisMessage .= INDENT."You can check certs at https://www.sslshopper.com/certificate-decoder.html\n\n";
				$analysisMessage .= INDENT."CURRENT (NEW) CERT(S):\n" .$certs."\n\n";
				$analysisMessage .= INDENT."Here is info from the interaction with the HTTPS server.\n\n";
				$needErrString = true;
			}
			// Save just the cert(s). (Only save if there IS a cert.)
			if ($certs != '') {
				$privateStore[$filterName][$domain]['SSL_BASELINE_CERT'] = $certs;
			}
		}
		else if (strcasecmp($privateStore[$filterName][$domain]['SSL_VERBOSE_RESULT'], $stderrString) != 0) {
			// The "verbose" result returned by the CURLOPT_SSL_VERIFY option is neither OK nor failed
			//   so report out what it contains.
			// This is a kind of ambiguous situation and many times we see this on a
			// cert that verifies just fine using other methods.
			$result = "Critical";
			$analysisMessage .= INDENT."Something is odd here. The certificate is neither 'valid' nor 'failed' - - \n";
			$analysisMessage .= INDENT."Examine the details carefully under 'CURRENT' below. \n";
			$analysisMessage .= INDENT."You can check the certs at https://www.sslchecker.com/certdecoder\n\n";
			$analysisMessage .= INDENT."Transcripts of the interactions with the HTTPS server appear below.\n\n";
			$analysisMessage .= INDENT."CURRENT (NEW) CERT(S):\n" .$certs."\n\n";
			$analysisMessage .= INDENT."PREVIOUS CERT(S):\n" .$privateStore[$filterName][$domain]['SSL_BASELINE_CERT']."\n\n";
			$needErrString = true;
		}
		$privateStore[$filterName][$domain]['SSL_VERBOSE_RESULT'] = $stderrString;
		// Add a common "analysis message" for all of the above
		if ($needErrString) {
			$analysisMessage = makeAnalysisMessage($analysisMessage, $certs, $analysis, $stderrString);
			$needErrString = false;
		}
	}
	catch (Exception $dax) {
		$result = "ssl";
		$message .= INDENT . "Exception in filter:SSL:SSLScan() $dax \n";
		echoIfVerbose("Exception in filter:SSL:SSLScan() $dax\n");	
	}

	if (!isNotifyHour($args['notify']) || ($result != 'OK')) {
		// Outside of the notify hour, or if the result is not 'OK', report the full 
		//   certificate analysis from libCurl
		//
		// During the notify hour, if the result is 'OK' then don't return any analysis
		//	 This makes for smaller notifications during the notification process, once a day,
		//   as long as the SSL keep checking out OK.
		$message .= $analysisMessage;
	}
	else {
		// Add an abbreviated message
		$message = trim($message , "\n");				// remove any excess trailing LF's
		$message .= "\n";								// need just one
		$message .= INDENT."The SSL certificate is valid. A full analysis will be sent if anything changes.\n";
	}
	
	$message = trim($message , "\n");				// remove any trailing LF
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