<?php
// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

////////////////////////////////////////////////////////////////////////
if (!function_exists('cs_http_get')) {
	function cs_http_get($url) {
		$result = '';
		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 				$url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,		true);
			if(defined('CS_HTTP_USER') && defined('CS_HTTP_PASS')) {
				// You can define user name and password in cs-log-pw.php
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($ch, CURLOPT_USERPWD, CS_HTTP_USER.':'.CS_HTTP_PASS);
			}
			$curlResult = curl_exec($ch);
			if ($curlResult === FALSE) {
				$result = "Error ".curl_errno($ch).": ".curl_error($ch);
			}
			else {
				$result = $curlResult;
			}
			curl_close($ch);
		}
		catch (Exception $chgx) {
			$result = '<!-- Exception: '.$chgx->getMessage()." -->\r\n";
		}
		return $result;
	}
}

/* Do not close the php */