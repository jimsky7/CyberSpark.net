<?php

////////////////////////////////////////////////////////////////////////
// Determine whether date is "NOW" or a specific calendar date
$calendar=false;
if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST')==0) {
//	echo '<!-- POST -->';
	if (isset($_POST['SUBMIT_CALENDAR'])) {
//		echo '<!-- SUBMIT_CALENDAR '.$_POST['SUBMIT_CALENDAR'].' -->';
		$calendar = true;
	}
	$direction = 0;
	if (isset($_POST['DIRECTION'])) {
//		echo '<!-- DIRECTION '.$_POST['DIRECTION'].' -->';
		if (strcasecmp($_POST['DIRECTION'], 'minus')==0) {
			$direction = -1;
			$calendar = true;
		}
		if (strcasecmp($_POST['DIRECTION'], 'plus')==0) {
			$direction = +1;
			$calendar = true;
		}
	}
	if (isset($_POST['SUBMIT_NOW'])) {
//		echo '<!-- SUBMIT_NOW -->';
	}
}
if (strcasecmp($_SERVER['REQUEST_METHOD'], 'GET')==0) {
//	echo '<!-- GET -->';
	if (($getYEAR = ifGetOrPost('YEAR')) != null && ($getMONTH = ifGetOrPost('MONTH')) != null && ($getDAY = ifGetOrPost('DAY')) != null) {
//		$_SESSION['YEAR'] = (int)$getYEAR;		// converts string to integer, avoid SQL injections
//		$_SESSION['MONTH']= (int)$getMONTH;		// converts string to integer, avoid SQL injections
//		$_SESSION['DAY']  = (int)$getDAY;		// converts string to integer, avoid SQL injections
		$calendar = true;		
	}
}
if (!$calendar) {
	$_SESSION['MONTH'] = date('m');
	$_SESSION['DAY']   = date('j');
	$_SESSION['YEAR']  = date('Y');
//	echo "<!-- DATE DEFAULTED TO 'now': $_SESSION[MONTH]-$_SESSION[DAY]-$_SESSION[YEAR] $_SERVER[REQUEST_METHOD]-->";
}

////////////////////////////////////////////////////////////////////////
$URLS 			= array();
$getDataURL		= array();
$sites			= array();

////////////////////////////////////////////////////////////////////////
// Set up dates+times based on span or explicit dates given
$startTimestamp = new DateTime();

$startDate 		= '';
$endDate   		= '';
$startTimestamp =  0;
$endTimestamp   =  0;
$MDY 			= '';

if ($calendar) {
//	echo '<!-- calendaring -->';
	$s = ifGetOrPost('MONTH');
	if ($s != null) {
		if (strlen($s) < 2) {
			$s = '0'.$s;			// add leading zero
		}
		$_SESSION['MONTH'] = $s;
	}
	else {
		if (!isset($_SESSION['MONTH'])) {
			$_SESSION['MONTH'] = '01';
		}
	}
//	echo "<!-- MONTH:$_SESSION[MONTH] -->";
	if (($s = ifGetOrPost('DAY')) != null) {
		if (strlen($s) < 2) {
			$s = '0'.$s;			// add leading zero
		}
		$_SESSION['DAY'] = $s;
		if ($_SESSION['DAY'] == 0) {
			$_SESSION['DAY'] = '01';
		}
	}
	else {
		if (!isset($_SESSION['DAY'])) {
			$_SESSION['DAY'] = '01';
		}
	}
//	echo "<!-- DAY:$_SESSION[DAY] -->";
	if (($s = ifGetOrPost('YEAR')) != null) {
		$_SESSION['YEAR'] = $s;
	}
	else {
		if (!isset($_SESSION['YEAR'])) {
			$_SESSION['YEAR'] = date('Y');
		}
	}
//	echo "<!-- YEAR:$_SESSION[YEAR] -->";
	$MDY = "MONTH=$_SESSION[MONTH]&DAY=$_SESSION[DAY]&YEAR=$_SESSION[YEAR]&";
}

////////////////////////////////////////////////////////////////////////
// Visual start-end dates
if ($calendar) {
	$dt   = new DateTime("$_SESSION[YEAR]-$_SESSION[MONTH]-$_SESSION[DAY] 00:00:00");
	$dtm2 = new DateTime("$_SESSION[YEAR]-$_SESSION[MONTH]-$_SESSION[DAY] 00:00:00");
	$dtm2->add(new DateInterval($span));
// Moving backward or forward in time?
	if ($direction > 0) {
		$dt->add(new DateInterval($span));
		$dtm2->add(new DateInterval($span));
	}
	if ($direction < 0) {
		$dt->sub(new DateInterval($span));
		$dtm2->sub(new DateInterval($span));
	}
	if ($direction) {
		$_SESSION['YEAR'] = $dt->format('Y');
		$_SESSION['MONTH']= $dt->format('m');
		$_SESSION['DAY']  = $dt->format('d');
	}
	$startTimestamp = ((int)$dt->format('U'))*1000;
	$endTimestamp   = ((int)$dtm2->format('U'))*1000;
	$startDate = $dt->format('d-M-Y').' [UTC]';
	$endDate   = $dtm2->format('d-M-Y').' [UTC]';

}
else {
	$dt = new DateTime;
	$endTimestamp = ((int)$dt->format('U'))*1000;
	$endDate = $dt->format('d-M-Y H:i');
	$dt->sub(new DateInterval($span));
	$startTimestamp = ((int)$dt->format('U'))*1000;
	$startDate = $dt->format('d-M-Y H:i');
}
//	echo "End: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";
//	echo "Start: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";

////////////////////////////////////////////////////////////////////////
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
