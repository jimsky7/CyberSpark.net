<?php
	/**
		CyberSpark.net monitoring-alerting system
		HTTP GET & POST
	*/

require_once 'HTTP/Client.php';
require_once 'HTTP/Request.php';

// See PEAR HTTP_Client documentation for details of supporting packages
// http://pear.php.net/manual/en/package.http.http-client.php

////////////////////////////////////////////////////////////////////////////////////
function httpGet($url, $userAgent, $timeout) {
	//// Use PEAR HTTP_Client to get a URL
	//   http://pear.php.net/manual/en/package.http.http-client.http-client.get.php
	try {

		$params = array();
		if ($timeout > 0) {
			$params['timeout'] = $timeout;		// in seconds
			$params['connect_timeout'] = $timeout;		// in seconds
//   follow_redirects = false;   'false' is the default
//   max_redirects = integer;    default is forever?
// timeout doesn't work; at least not this way
// echo "Set timeout in seconds = " . $timeout . "\n";
		}

		$headers = array();
		$headers['User-Agent'] = $userAgent;
		$headers['Connection'] = 'close';
		$headers['Accept'] = 'text/html,text/plain';	// Need this for compatibility and FOR CLOUDFLARE
														//   (otherwise get a 403 from cloudflare)
		$startingBracket = strpos($url, "[");
		$endingBracket = strpos($url, "]");
		if (($startingBracket !== false) && ($endingBracket !== false)) {
			$host = substr($url, $startingBracket+1, ($endingBracket-$startingBracket-1));
			$url = substr($url, $endingBracket+1);
			echoIfVerbose ("Connecting to: $url \n  and requesting this virtual host: $host\n");
			$headers['Host'] = $host;
		}
		
		

		// Make the request
		////////////////////
		// GET
		try {
			$httpClient = new HTTP_Client($params, $headers);
			$httpResult = $httpClient->get($url);

			if(PEAR::isError($httpClient)) { 
				return array('code'=>0,'error'=>'Exception[1] GET in httpGet: '.$x->getMessage());
			}
			// result is associative array containing 'code' 'headers' and 'body'
			return $httpClient->currentResponse();		
		}
		catch (Exception $x) {
			return array('code'=>0,'error'=>'Exception[2] GET in httpGet: '.$x->getMessage());
		}
	}
	catch (Exception $mx) {
		return array('code'=>0,'error'=>'Exception in httpGet: '.$mx->getMessage());
	}
}


////////////////////////////////////////////////////////////////////////////////////
function httpPost($url, $userAgent, $timeout) {
	//// Use PEAR HTTP_Client to post to a URL
	//   http://pear.php.net/manual/en/package.http.http-client.http-client.post.php
	try {

		$params = array();
		if ($timeout > 0) {
			$params['timeout'] = $timeout;		// in seconds
		}
// timeout doesn't work; at least not this way
// echo "Set timeout in seconds = " . $timeout . "\n";
	
		$headers = array();
		$headers['User-Agent'] = $userAgent;
		$headers['Connection'] = 'close';
		$headers['Accept'] = 'text/html,text/plain';	// Need this for compatibility and FOR CLOUDFLARE
														//   (otherwise get a 403 from cloudflare)
		// Check for POST parameters in the URL (indicated by "?" in the URL
		if (($pos = strpos($url, "?", 0)) !== false) {
			$postString = urlencode(substr($url, $pos+1));
// Note: Leave the URL intact by commenting out the line below,
//   because we don't know whether the target server is expecting them or not.
//			$url = substr($url, 0, $pos);		
		}

		// Make the request
		////////////////////
		// POST
		try {
			$httpClient = new HTTP_Client($params, $headers);
			$httpResult = $httpClient->post($url, $postString, true);	// is already urlencoded

			if(PEAR::isError($httpClient)) { 
				return array('code'=>0, 'error'=>'Exception[1] POST in httpPost: '.$x->getMessage());
			}
			// result is associative array containing 'code' 'headers' and 'body'
			return $httpClient->currentResponse();		
		}
		catch (Exception $x) {
			return array('code'=>0, 'error'=>'Exception[2] POST in httpPost: '.$x->getMessage());
		}
	}
	catch (Exception $mx) {
		return array('code'=>0,'error'=>'Exception in httpPost: '.$mx->getMessage());
	}
}


?>