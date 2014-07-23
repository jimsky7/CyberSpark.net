#!/usr/bin/php -q
<?php

/**** 
	CyberSpark.net log daemon 
	Monitor local logs, send entries to central repository.
****/

require_once 'HTTP/Client.php';
require_once 'HTTP/Request.php';
require_once 'log-transport-config.php';		// same directory as this script
require_once 'log-transport-pw.php';				// same directory as this script
require_once 'include/http.inc';				// HTTP functions
require_once 'include/args.inc';
require_once 'cyberspark.sysdefs.php';

declare(ticks = 1);					// allows shutdown functions to work

$ID = '';
$pipes = null;

list($isDaemon, $ID) = getArgs($argv);

if ($ID == null || strlen($ID) == 0) {
	die ('Parameter --id is required');
}

function shutdownLogTransportFunction($sig) {
	global $ID;
	global $LTpidFileName;
	if ($sig === SIGINT || $sig === SIGTERM) {
		echo "\nLog transport $ID [signal=$sig]\n";
		try {
			@unlink($LTpidFileName);
		}
		catch (Exception $x) {
		echo "Warning: $ID log transport unable to delete process ID file.\n";
		}
		echo "$ID log transport is exiting.\n";
		exit;
	}
}

// Register shutdown functions
try {
	pcntl_signal(SIGTERM, 'shutdownLogTransportFunction');		// kill
	pcntl_signal(SIGINT,  'shutdownLogTransportFunction');		// Ctrl-C
}
catch (Exception $x) {
	echo "Critical: $ID log transport was unable to register shutdown functions.\n";
}

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
$fn 				= "log/$ID.log";							// note: relative to current dir
$LTpidFileName = $ID . '-transport' . PID_EXT;		// pid file goes into local dir

// Write process ID to a file
// Note that if we were run from cybersparkd.php then this pid file is critical because
//   our parent is an 'sh' and not cybersparkd itself.  So the pid file is the only way
//   cybersparkd can find and terminate this script.
try {
	@unlink($LTpidFileName);
}
catch (Exception $x) {
	echo "Warning: $ID unable to delete process ID file.\n";
}
try {
	@file_put_contents($LTpidFileName, (string)posix_getpid());	// save process ID
//	echo("Wrote pid file $LTpidFileName \n");
}
catch (Exception $x) {
	echo "Critical: $ID log transport unable to write process ID to 'pid' file.\n";
}
	
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
				// Note that PEAR isn't used here because our version doesn't do Basic Auth.
				try {
					$ch = curl_init();
        			curl_setopt($ch, CURLOPT_POST, 1);
					$u = $uploadURL;
					$ueData = 'header='.urlencode($csvHeader).'&log='.urlencode($s);
					curl_setopt($ch, CURLOPT_URL, 				$u);
					if(defined('CS_HTTP_USER') && defined('CS_HTTP_PASS')) {
						// You can define user name and password in cs-log-pw.php
						curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
						curl_setopt($ch, CURLOPT_USERPWD, CS_HTTP_USER.':'.CS_HTTP_PASS);
					}
					curl_setopt($ch, CURLOPT_POSTFIELDS, 		$ueData); 
					curl_setopt($ch, CURLOPT_RETURNTRANSFER,		1);
        			curl_setopt($ch, CURLOPT_HEADER, 			0);
					curl_setopt($ch, CURLOPT_HTTPHEADER, 		array('Content-Length: '.strlen($ueData)));
					$curlResult = curl_exec($ch);
					if ($curlResult === FALSE) {
					}
					curl_close($ch);
				}
				catch (Exception $chgx) {
				}

// >>> Nah, the basic auth doesn't work in this PEAR package
//				$result = httpPostParams($uploadURL, null, $params, $timeout, $auth);
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
