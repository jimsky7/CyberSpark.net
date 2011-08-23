<?php
	/**
		CyberSpark.net monitoring-alerting system
		handle command-line arguments
	*/


///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
function echoIfVerbose($string) {
	global $properties;
	
	if (isset($properties['verbose']) && $properties['verbose']) {
		echo $string;
	}
}

///////////////////////////////////////////////////////////////////////////////////
function print_rIfVerbose($structure) {
	global $properties;
	
	if (isset($properties['verbose']) && $properties['verbose']) {
		print_r($structure);
	}
}

///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
function beginLog() {
	global $logFileName;
	global $logHandle;

	// Note: When this is run, $properties aren't set yet so we don't know any thread IDs
	try {
		$fileExists = file_exists($logFileName);
		$logHandle = fopen($logFileName, 'ab');		// open for 'append'
		echoIfVerbose("Opening log file: $logFileName\n");
		if (!$fileExists) {
			$message = "milliseconds,date,host,thread,tick,crashes,http_ms,length,md5,condition,url,result_code,year,month,day,hour,minute,second,APIusage,full_message";
			$count = fwrite($logHandle, ($message . "\r\n"));
		}
	}
	catch (Exception $x) {
		echo "Failed to open log file $logFileName\n";
	}
}

///////////////////////////////////////////////////////////////////////////////////
function endLog() {
	global $logFileName;
	global $logHandle;
	
	echoIfVerbose("Closing log file: $logFileName\n");
	writeLogAlert("End");
	if (isset($logHandle)) {
		fflush($logHandle);
		fclose($logHandle);
	}
}

///////////////////////////////////////////////////////////////////////////////////
function writeLogAlert($message) {
	writeLog($message, 0, 0, "", "", "", "", 0);
}

///////////////////////////////////////////////////////////////////////////////////
function writeLog($message, $elapsedTime, $length, $md5, $condition, $url, $code, $apiCount) {
	global $logHandle;
	global $properties;

	if (isset($logHandle)) {
		list($usec, $sec) = explode(" ", microtime());
		$milliseconds = "$sec" . sprintf("%03d", ($usec*1000));
		$date = "\"" . date("r") . "\"";
		
		$host = safeQuote($properties['host']);
		$thread = safeQuote($properties['shortid']);
		$tick = $properties['loop'];
		$crashes = $properties['crashes'];
		$http_ms = $elapsedTime;
		$length_of_body = $length;
		$md5 = $md5;
		$condition = safeQuote($condition);
		$url = safeQuote($url);
		$result_code = $code;
		
		$ymdhms = date('Y,m,d,H,i,s');	// quickly create six fields here
		
		$APIusage = $apiCount;
		$safe_message = safeQuote(str_replace(array("\r", "\n"), " ", $message));

		$mess = "$milliseconds,$date,$host,$thread,$tick,$crashes,$http_ms,$length_of_body,$md5,$condition,$url,$result_code,$ymdhms,$APIusage,$safe_message";
		$count = fwrite($logHandle, ($mess . "\r\n"));
	}
}

?>