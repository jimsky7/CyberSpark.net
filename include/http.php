<?php
	/**
		CyberSpark.net monitoring-alerting system
		HTTP GET & POST
		using php5_curl
	*/

////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////
// Make GET and POST requests using php5_curl
// 		(See http://php.net/manual/en/ref.curl.php )
// Package is built into PHP5, so no need to use PEAR any more.
////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////

require_once('cyberspark.sysdefs.php');

////////////////////////////////////////////////////////////////////////////////////
// Manufacture common headers for CyberSpark GET and POST
// May alter $url in the process (NOTE: $url is called by reference)
function setCommonHeaders(&$url, $options=null) {
	$headers = array();
	// Need "Accept:" for compatibility and FOR CLOUDFLARE, otherwise get 403
	$headers['Accept'] = 'text/html,text/plain';	
	// IP address plus "Host:" header is requested by this form where the "Host:" value is in brackets
	// http://[web.red7.com]173.45.230.19/home.php
	$startingBracket = strpos($url, "[");
	$endingBracket = strpos($url, "]");
	if (($startingBracket !== false) && ($endingBracket !== false)) {
		$host = substr($url, $startingBracket+1, ($endingBracket-$startingBracket-1));
		$url  = substr($url, $endingBracket+1);
//		echo ("Connecting to: $url \n  and requesting this virtual host: $host\n");
		$headers['Host'] = $host;
	}
	// Return completed basic/common headers for CyberSpark spidering
	return $headers;
}

////////////////////////////////////////////////////////////////////////////////////
// Set cURL options that we want for both GET and POST
function setCommonOptions(&$ch, $url, $auth, $headers, $timeout, $userAgent, $sslVerify=false, $options=null) {
	curl_setopt($ch, CURLOPT_URL, $url);
	if (($auth != null) && isset($auth['CS_HTTP_USER']) && isset($auth['CS_HTTP_PASS'])) {
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $auth['CS_HTTP_USER'].':'.$auth['CS_HTTP_PASS']);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,			1);		// return HTTP result rather than display it
   	curl_setopt($ch, CURLOPT_HEADER, 				1); 	// Return all headers
	curl_setopt($ch, CURLOPT_FORBID_REUSE,			1); 	// Connection: close
	if (($options != null) && isset($options['maxredirects'])) {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 	1);		// Follow this many redirects
		curl_setopt($ch, CURLOPT_MAXREDIRS,    $options['maxredirects']);	// Limit number of redirects
	}
	else {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,		0);	// Do not follow any redirects, report them
	}
	// Misc headers
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	// Timeout: There is a timeout if HTTP doesn't connect, and a separate timeout if the server is slow.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,  (($timeout>0)?$timeout:DEFAULT_SOCKET_TIMEOUT));
    curl_setopt($ch, CURLOPT_TIMEOUT,		  (($timeout>0)?$timeout:DEFAULT_SOCKET_TIMEOUT));
	// User agent
	if (isset($userAgent) && ($userAgent != null) && (strlen($userAgent) > 0)) {
		curl_setopt($ch, CURLOPT_USERAGENT,		$userAgent);
	}
	// SSL should or should not strictly verify the SSL cert. 
	// Usually not, because the SSL filter is used to do the exhaustive checking.
	// Note that the SSL filter does not use any functions from this file.
	// It does its own cURL connections.
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify);
}

////////////////////////////////////////////////////////////////////////////////////
// Common result processing for both GET and POST
function commonResult($ch, $curlResult, $options=null) {

	$cen  = curl_errno($ch);
	$cerr = curl_error($ch);
	if($cen) {
		// cURL error
		return array('code'=>$cen, 'error'=>$cerr, 'headers'=>array(), 'curl_info'=>array(), 'body'=>'');
	}

	$curl_info = curl_getinfo($ch);	// See http://us1.php.net/manual/en/function.curl-getinfo.php

	$headers = array();
	$body = '';
	// Separate into headers and body
	$a = getHeadersAndBody($curlResult);
	if ($a != null) {
		$headers = $a['headers'];
		$body    = $a['body'];
	}
			
	$http_code = $curl_info['http_code'];
	if ($http_code >= 300 && $http_code <= 399) {
		// Redirect: return the redirect URL as the body
		return array('code'=>$http_code, 'error'=>'', 'headers'=>$headers, 'curl_info'=>$curl_info, 'body'=>$curl_info['redirect_url']);
	}
	if ($http_code >= 400 && $http_code <= 499) {
		// Not found:
		return array('code'=>$http_code, 'error'=>'', 'headers'=>$headers, 'curl_info'=>$curl_info, 'body'=>$body);
	}
	if ($http_code >= 500 && $http_code <= 599) {
		// Server/gateway error:
		return array('code'=>$http_code, 'error'=>'', 'headers'=>$headers, 'curl_info'=>$curl_info, 'body'=>$body);
	}
	// Other (including 200 OK)
	return array('code'=>$http_code, 'error'=>'', 'headers'=>$headers, 'curl_info'=>$curl_info, 'body'=>$body);
}

////////////////////////////////////////////////////////////////////////////////////
// Take a cURL result (from GET or POST) and separate into 'headers' and 'body'
function getHeadersAndBody($s) {
	$headers = array();
	$body = '';
	try {
		if (isset($s) && ($s != null) && (strlen($s) > 0)) {
			// Convert to Unix text EOL standard
			$stxt = str_replace("\r\n", "\n", $s);		// CRLF (Windows)     => LF
			$stxt = str_replace("\r",   "\n", $stxt);	// raw CR (Macintosh) => LF
			// Find the break between the returned headers and the body
			$doubleCR = strpos($stxt, "\n\n");
			if ($doubleCR === false) {
				// Huh? Assume all body, no headers
				return array('headers'=>array(), 'body'=>$stxt);	// Note the 'Unix' $st is returned
			}
			// Separate the headers into an array
			$heads = explode("\n", substr($stxt, 0, $doubleCR));
			foreach ($heads as $value) {
				$head = explode(':', $value, 2);
				if (isset($head[1])) {
					$headers[$head[0]] = trim($head[1]);
				}
			}
			$body = substr($stxt, ($doubleCR+2));
		}
	}
	catch (Exception $sHx) {
		// Nothing. Everything was initialized just in case.
	}
	return array('headers'=>$headers, 'body'=>$body);
}

////////////////////////////////////////////////////////////////////////////////////
// php5_curl GET
function curlGet($url, $userAgent, $timeout, $auth=null, $sslVerify=false, $options=null) {
	//// Use PHP5 cURL class to get a URL
	// See http://us1.php.net/manual/en/ref.curl.php
	// Result is associative array containing 'code' 'headers''body' 'curl_info'
	// Note that we also return the 'curl_info' which may be used (but is not as of 2014-10-02)
	try {
		$headers = setCommonHeaders($url, $options);
		$ch = curl_init();
		if ($ch !== false) {
			setCommonOptions($ch, $url, $auth, $headers, $timeout, $userAgent, $sslVerify, $options);
			// Execute the GET
			$result = commonResult($ch, curl_exec($ch), $options);			
			// Close cURL
			curl_close($ch);
			// Done
			return $result;
		}
	}
	catch (Exception $gx) {
		return array('code'=>0, 'error'=>'Exception[2] GET in curlGet: '.$gx->getMessage(), 'headers'=>array(), 'body'=>'');
	}
	// Couldn't open php5 cURL
	return array('code'=>0, 'error'=>'cURL: Unable to initialize in curlGet()', 'headers'=>array(), 'body'=>'');
}

////////////////////////////////////////////////////////////////////////////////////
// php5_curl POST
// With an explicit array of parameters
function curlPost($url, $userAgent, $paramArray, $timeout, $auth=null, $sslVerify=false, $options=null) {
	//// Use PHP5 cURL class to get a URL
	// See http://us1.php.net/manual/en/ref.curl.php
	try {
		$headers = setCommonHeaders($url, $options);
			
		// Assemble POST parameters into a long string 
		// They will go into the body of the POST
		// Note that the values are URL-encoded
		$postString = '';
		if ($paramArray != null && count($paramArray) > 0) {
			foreach ($paramArray as $param=>$value) {
				$postString .= $param.'='.urlencode($value).'&';
			}
		}
		
		$ch = curl_init();
		if ($ch !== false) {
			setCommonOptions($ch, $url, $auth, $headers, $timeout, $userAgent, $sslVerify, $options);
        	// Turn this into a POST
			curl_setopt($ch, CURLOPT_POST, 				1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, 		$postString); 
			// Execute
			$result = commonResult($ch, curl_exec($ch), $options);			
			// Close cURL
			curl_close($ch);
			// Done
			return $result;
		}
	}
	catch (Exception $gx) {
		return array('code'=>0, 'error'=>'Exception[2] GET in curlGet: '.$gx->getMessage(), 'headers'=>array(), 'body'=>'');
	}
	// Couldn't open php5 cURL
	return array('code'=>0, 'error'=>'cURL: Unable to initialize in curlGet()', 'headers'=>array(), 'body'=>'');
}

////////////////////////////////////////////////////////////////////////////////////
// php5_curl POST
// With params sucked from "?" in the URL (if any)
function curlPostParamsFromURL($url, $userAgent, $timeout, $auth=null, $sslVerify=false, $options=null) {
	//// Use PHP5 cURL class to get a URL
	// See http://us1.php.net/manual/en/ref.curl.php

	// Check for POST parameters in the URL (indicated by "?" in the URL
	if (($pos = strpos($url, "?", 0)) !== false) {
		$p = explode('&', substr($url, ($pos+1)));
		foreach ($p as $param) {
			$ps = explode('=', $param, 2);
			if (isset($ps[1])) {
				$paramArray[$ps[0]] = $ps[1];
			}
			else {
				$paramArray[$ps[0]] = 1;
			}
		}
	}
	else {
		$paramArray = array();
	}
	// Do a POST
	return curlPost($url, $userAgent, $paramArray, $timeout, $auth, $sslVerify, $options);
}

////////////////////////////////////////////////////////////////////////////////////
//	Conform to pre-2014 function definitions
//  Note that SSL verification is suppressed
function httpGet($url, $userAgent, $timeout, $auth=null, $options=null) {
	return curlGet($url, $userAgent, $timeout, $auth, false, $options);
}


////////////////////////////////////////////////////////////////////////////////////
//	Conform to pre-2014 function definitions
//  Note that SSL verification is suppressed
function httpPost($url, $userAgent, $timeout, $auth=null, $options=null) {
	return curlPostParamsFromURL($url, $userAgent, $timeout, $auth, false, $options);
}

////////////////////////////////////////////////////////////////////////////////////
//	Conform to pre-2014 function definitions
//  Note that SSL verification is suppressed
function httpPostParams($url, $userAgent, $paramArray, $timeout, $auth=null, $options=null) {
	return curlPost($url, $userAgent, $paramArray, $timeout, $auth, false, $options);
}
?>