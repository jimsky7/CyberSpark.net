#!/usr/bin/php -q
<?php

/**** 
	CyberSpark.net log daemon 
	Monitor local logs, send entries to central repository.
****/

require_once 'HTTP/Client.php';
require_once 'HTTP/Request.php';
require_once 'log-transport-config.php';		// same directory as this script
require_once 'include/http.inc';			// HTTP functions

// >>> Next line must come from somewhere else
// >>> OR search the directory for all possible logs and 'tail' each one of them
$fn 				= '/usr/local/cyberspark/log/CS3-10.log';
//
$uploadURL		= CS_URL_POST;
$maxLines   	= 0;			// max number of lines to process (zero means no limit)
$i          	= 0;			// number of lines processed
$fpos       	= 0;			// file position
$defaultSleep	= 20;			// default sleep time
$longSleep  	= 60;
$sleepTime  	= $defaultSleep;
$timeout		= 20;
$userAgent		= '';
$csvHeader		= null;

// Without the log open, monitor its length.
// If the length increases, then open the file and read/process records.
while (($maxLines==0) || ($i<$maxLines)) {
    $i++;
	clearstatcache(false, $fn);
	$length = filesize($fn);
	// If file is shorter now than before, then assume it's new
	if ($length < $fpos) {
		// File was recreated (shortened)
		$fpos = 0;			// back to the beginning
	}
	// If file is empty, wait a while
	if ($length == 0) {
		$fpos = 0;			// back to the beginning
		$sleepTime = $defaultSleep;
		continue;
	}
	// Get header if don't have it yet
	if ($csvHeader == null) {
		$f  = fopen($fn,'r');
		if ($f !== false) {
			$csvHeader = fgets($f);
			$fpos = ftell($f);
			fclose($f);		
		}
	}
	// Process next log entry
	$f  = fopen($fn,'r');
	if ($f !== false) {
		$sleepTime = $defaultSleep;
		fseek($f, $fpos);
		
		while(true) {
			$s = fgets($f);
	
    		if (feof($f) || ($s===false)) {
				break;				// stop reading at end of file
		    }
    		else {
				// Send one log entry
				$params = array();
				$params['header'] = $csvHeader;
				$params['log']    = $s;
				$result = httpPostParams($uploadURL, null, $params, $timeout);
//    	    	echo $s."\r\n";
    		}	
		}
		$fpos = ftell($f);
		fclose($f);
	}
	else {
		$sleepTime = $longSleep;
	}
    sleep($sleepTime);
}

?>
