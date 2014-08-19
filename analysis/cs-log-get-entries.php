<?php
/**** cs-log-get-entries.php

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

	This script reads CyberSpark log data from MySQL for use by
	CyberSpark visualizations and analyzers.
	
	You have to make a web <FORM> to target this script. Filename must include full path.
	
	These fields are present in the `logs` table (every record is a fixed length):
		ID				tick			condition	 	day
		URL_HASH		crashes			URL_ID			hour
		milliseconds	http_ms			result_code		minute
		date			length			year			second
		host			md5				month			APIusage
		thread
	The table `urls` contains URLs and their hashes (ID corresponds to URL_ID in `logs`:
		ID				URL_HASH		url									
	The table `messages` contains IDs and messages (ID corresponds to ID in `logs`:
		ID				message			

mysqli is documented here => http://us2.php.net/manual/en/class.mysqli.php

	$_POST parameters (suggest that you use a web <FORM> or Ajax):
		?URL_HASH=
			md5 HASH of the URL for which you want data. (As we store it internally.)
		?span=nD/nH/nW/nM
			Specifies how many days (D) hours(H) weeks(W) or months (M) of data you want.
			You may use only one of these specifiers.
		?limit=nnnn
			Specifies a maximum number of entries to retrieve for the URL.
			
Note this uses ob_start() meaning that the size of output may be limited by RAM available or
some other consideration I'm not aware of.

****/

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Setup
include('cs-log-config.php');
include('cs-log-functions.php');
include('cs-log-pw.php');
ini_set('auto_detect_line_endings', true);

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Parameters
if (false) {
	header('Content-type: text/plain');
	echo "### Good morning, Dr.\r\n";
}
$URL_HASH 	= ifGetOrPost('URL_HASH');
$span 		= ifGetOrPost('span');
$API_KEY 	= ifGetOrPost('API_KEY');
$format 		= ifGetOrPost('format');
$limit 		= ifGetOrPost('limit');
$pad 		= ifGetOrPost('pad');

$DAY		= ifGetOrPost('DAY');
$MONTH		= ifGetOrPost('MONTH');
$YEAR		= ifGetOrPost('YEAR');

if (($pad != null) && ((strcasecmp('true',$pad)==0) || (strcasecmp('1',$pad)==0) || (strcasecmp('yes',$pad)==0))) {
	$pad=true;
}
else{
	$pad=false;
}

$startDate = '';
$endDate   = '';
$startTimestamp =  0;
$endTimestamp   =  0;
$paddedStart    = false;		// goes 'true' when start has been completely padded out

// Validate 'API_KEY'
// Since this is protected by HTTP Basic Authentication, we don't really need a CS_API_KEY.
// But go for it if you wish.
//	if (isset($CS_API_KEYS) && (!isset($API_KEY) || ($API_KEY==null) || (!in_array($API_KEY, $CS_API_KEYS, true)))) {
//		die("Error: An API key is required");
//	}

// Validate format
if (!isset($format) || ($format == null)) {
	$format = 'csv';
}

// Validate 'span'
// Short
// Begins with 'P'
// >>>


// Validate 'URL_HASH'
// 32 characters
// convert to lower case
// HEX only
// >>>

// Convert 'limit'
if ($limit != null) {
	$limit = (int) $limit;
}

// TEST
// http://example.com/analysis/cs-log-get-entries.php?URL_HASH=b3eaf4bce2b85708a6c930a5d527340d&span=P1W&format=csv
// http://example.com/analysis/cs-log-get-entries.php?URL_HASH=b3eaf4bce2b85708a6c930a5d527340d&span=P1W&format=csv&pad=1
// TEST

if ($URL_HASH == null || $span == null || strlen($span)<2) {
	die("Error: Required parameters are missing");
}

// Desired output field names in the order you want them sent to the database
// You don't have to emit everything that was read from the log
$outputFieldNames = array (
	0=>'URL_HASH', 1=>'milliseconds', 2=>'date', 
	3=>'http_ms', 4=>'URL_ID', 5=>'result_code',
	6=>'year', 7=>'month', 8=>'day',
	9=>'hour', 10=>'minute', 11=>'second'
);
// Use TRUE or FALSE for each depending on whether you want a field quoted in the output
$outputFieldQuote = array (
	0=>true, 1=>false, 2=>true, 
	3=>false, 4=>false, 5=>false, 
	6=>false, 7=>false, 8=>false,
	9=>false, 10=>false, 11=>false
);

$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);

if ($mysqli == null) {
	die("Error: Couldn't connect to MySQL on ".MYSQL_HOST." with user name and password specified.");
}

ob_start();

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Determine the milliseconds we want to go back to in the data
// Always start from the present
$dt = new DateTime;
$endTimestamp   = ((int)$dt->format('U'))*1000;
//	echo "End: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";
$dt->sub(new DateInterval($span));
//	echo "Start: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";
$startTimestamp = ((int)$dt->format('U'))*1000;

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// A specific date requested?
if ($MONTH != null && $DAY != null && $YEAR != null) {
	$dtm1 = new DateTime("$YEAR-$MONTH-$DAY 00:00:00");
	$dtm2 = new DateTime("$YEAR-$MONTH-$DAY 00:00:00");
	$dtm2->add(new DateInterval($span));
	$startTimestamp = ((int)$dtm1->format('U'))*1000;
	$endTimestamp   = ((int)$dtm2->format('U'))*1000;
}

//	echo "Start: $startTimestamp <br/>\r\n";
//	echo "End:  $endTimestamp <br/>\r\n";
//	echo "Diff: " . ($endTimestamp-$v) . "<br/>\r\n";

//	echo "URL_HASH: $URL_HASH <br/> \r\n";
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Set up to retrieve using SQL

$query = "SELECT `milliseconds`,`http_ms`,`result_code`,`date`,`year`,`month`,`day`,`hour`,`minute`,`second` FROM `logs` WHERE (`URL_HASH` = ? AND `milliseconds` >= ? AND `milliseconds` <= ?)";
if ($limit != null) {
	$query .= " LIMIT ?";
}

$stmt =  $mysqli->stmt_init();
$result = $stmt->prepare($query);
if ($limit != null) {
	$result = $stmt->bind_param('siii', $URL_HASH, $startTimestamp, $endTimestamp, $limit);
}
else {
	$result = $stmt->bind_param('sii', $URL_HASH, $startTimestamp, $endTimestamp);
}
$result = $stmt->execute();
if ($stmt->errno) {
	echo "Error: [alert] number ".$stmt->errno." <br/>\r\n";
	echo "Error: [alert] message ".$stmt->error." <br/>\r\n";
	die ("Program ended.");
}
$result = $stmt->bind_result($milliseconds,$http_ms,$result_code,$date,$year,$month,$day,$hour,$minute,$second) ;
$result   = $stmt->store_result();

if ($result) {

	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// File 'header'
	// Note that the header is sent even if there are no rows retrieved
	switch ($format) {
		case 'csv': {
			echo "milliseconds,URL_HASH,http_ms,result_code,date,year,month,day,hour,minute,second\r\n";
			break;
		}
		case 'tsv': {
			echo "milliseconds\tURL_HASH\thttp_ms\tresult_code\tdate\tyear\tmonth\tday\thour\tminute\tsecond\r\n";
			break;
		}
	}

	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// Lines of file
	if ($stmt->num_rows > 0) {
		while($stmt->fetch()) {
			// After the ->fetch() all data has been transferred to local vars
			$millisecondsPad = $startTimestamp;
			while ($pad && !$paddedStart && ($milliseconds > ($millisecondsPad+(PAD_VALUE*1000)))) {
				// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
				// Pad the beginning?
				// Causes the chart to always start at the begining, by ensuring initial entries
				$ts = $millisecondsPad/1000;
				$http_msPad = -0.1;							// this should be an invisible value
				$yearPad   = date('Y', $ts);
				$monthPad  = date('m', $ts);
				$dayPad    = date('d', $ts); 
				$hourPad   = date('H', $ts);
				$minutePad = date('i', $ts);
				$secondPad = date('s', $ts);
				$datePad   = date('r', $ts);
				$result_codePad = PAD_CODE;						// this is fake, there is no real response, is there better approach?
				switch ($format) {
					case 'csv': {
						echo "$millisecondsPad,$URL_HASH,$http_msPad,$result_codePad,\"$datePad\",$yearPad,$monthPad,$dayPad,$hourPad,$minutePad,$secondPad\r\n";
						break;
					}
					case 'tsv': {
						echo "$millisecondsPad\t$URL_HASH\t$http_msPad\t$result_codePad\t\"$datePad\"\t$yearPad\t$monthPad\t$dayPad\t$hourPad\t$minutePad\t$secondPad\r\n";
						break;
					}
				}
				$millisecondsPad += PAD_VALUE*1000;
			}
			$paddedStart = true;							// done padding, or padding was not needed
	
			switch ($format) {
				case 'csv': {
					echo "$milliseconds,$URL_HASH,$http_ms,$result_code,\"$date\",$year,$month,$day,$hour,$minute,$second\r\n";
					break;
				}
				case 'tsv': {
					echo "$milliseconds\t$URL_HASH\t$http_ms\t$result_code\t\"$date\"\t$year\t$month\t$day\t$hour\t$minute\t$second\r\n";
					break;
				}
			}
		}
	}
	else {
		// No data retrieved. Allow padding to happen (if it was requested)
		$milliseconds = $startTimestamp;
	}

	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// Pad the end?
	$millisecondsPad = $milliseconds + (PAD_VALUE*1000);
	while ($pad && ($endTimestamp > ($millisecondsPad+(PAD_VALUE*1000)))) {
		$ts = $millisecondsPad/1000;
		$http_msPad = -0.1;							// this should be an invisible value
		$yearPad   = date('Y', $ts);
		$monthPad  = date('m', $ts);
		$dayPad    = date('d', $ts); 
		$hourPad   = date('H', $ts);
		$minutePad = date('i', $ts);
		$secondPad = date('s', $ts);
		$datePad   = date('r', $ts);
		$result_codePad = PAD_CODE;						// this is fake, there is no real response, is there better approach?
		switch ($format) {
			case 'csv': {
				echo "$millisecondsPad,$URL_HASH,$http_msPad,$result_codePad,\"$datePad\",$yearPad,$monthPad,$dayPad,$hourPad,$minutePad,$secondPad\r\n";
				break;
			}
			case 'tsv': {
				echo "$millisecondsPad\t$URL_HASH\t$http_msPad\t$result_codePad\t\"$datePad\"\t$yearPad\t$monthPad\t$dayPad\t$hourPad\t$minutePad\t$second\r\n";
				break;
			}
		}
		$millisecondsPad += (PAD_VALUE*1000);
	}

	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// File 'footer'
	switch ($format) {
		case 'csv': {
			break;
		}
		case 'tsv': {
			break;
		}
	}
	
}
$stmt->close();


// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Make output

header('Content-type: text/plain');
ob_end_flush();

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// All done

$mysqli->close();

?>