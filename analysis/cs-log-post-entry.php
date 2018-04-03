<?php
/**** 
	Capture a log entry
	via HTTP POST ... parameters are
		CS_API_KEY		=	a minimal secret key required to post anything
		log	=			=	the log information, CSV (field may be quoted)
		header			=	the field names, CSV (names may be quoted)
	In this code we use an MD5 hash of the incoming URL to determine where to put data
	in the database. Keep that in mind. This hash is used lots of places as an identifier.
****/

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/
include('cs-log-config.php');
include('cs-log-functions.php');
include('cs-log-pw.php');

// >>> For production
// >>> Add the log entry to the database

// Desired output field names in the order you want them sent to the database
// You don't have to emit everything that was read from the log
$outputFieldNames = array (0=>'milliseconds', 1=>'date', 2=>'host', 3=>'thread',
	4=>'tick', 5=>'crashes', 6=>'http_ms', 7=>'length', 8=>'md5',
	9=>'condition', 10=>'URL_ID', 11=>'result_code', 12=>'year', 13=>'month', 14=>'day',
	15=>'hour', 16=>'minute', 17=>'second', 18=>'APIusage'
);
// Use 's' 'i' 'd' depending on type of field corresponding to $outputFieldNames[]
//   ('s' is string; 'i' is integer; 'd' is double)
$outputFieldType = array ('milliseconds'=>'i', 'date'=>'s', 'host'=>'s', 'thread'=>'s',
	'tick'=>'i', 'crashes'=>'i', 'http_ms'=>'d', 'length'=>'i', 'md5'=>'s',
	'condition'=>'s', 'URL_ID'=>'s', 'result_code'=>'i', 'year'=>'i', 'month'=>'i', 'day'=>'i',
	'hour'=>'i', 'minute'=>'i', 'second'=>'i', 'APIusage'=>'i'
);
$outputFieldTypes = '';
foreach ($outputFieldNames as $key=>$value) {
	$outputFieldTypes .= $outputFieldType[$value];
}

// Authentication (it's minimal, but it's something)
// POST Parameter CS_API_KEY must match an entry in $CS_API_KEYS (if defined)
if (isset($CS_API_KEYS)) {
	// Does param CS_API_KEY exist?
	if (!isset($_POST['CS_API_KEY'])) {
		header($_SERVER['SERVER_PROTOCOL'].' 401 Not Authorized', true, 401);
		exit;
	}
	$API_KEY = $_POST['CS_API_KEY'];
	// Does param match? (strict comparison)
	if (($API_KEY==null) || !in_array($API_KEY, $CS_API_KEYS, true)) {
		header($_SERVER['SERVER_PROTOCOL'].' 401 Not Authorized', true, 401);
		exit;
	}
}

// POST items 'log' and 'header' are required
if (!isset($_POST['log']) || !isset($_POST['header'])) {
	header($_SERVER['SERVER_PROTOCOL'].' 401 Not Authorized', true, 401);
	exit;
}

ob_start();
	
$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);

// Process first line of log file, which contains field names
$line = $_POST['header'];
$lineArray = str_getcsv($line, ',', '"');
$line = array();
foreach($lineArray as $key=>$value) {
	$line[$key] = $value;
}
$fieldNames = array();
//$IDforHASH  = array();	// key is URL_HASH, value is URL_ID corresponding to row number in `urls` table
// Capture field names, in order, from the log file (note we preserve case)
foreach ($line as $field) {
	$fieldNames[] = $field;
}	
$fieldNames = array_flip($fieldNames);

$line = $_POST['log'];
$lineArray = str_getcsv($line, ',', '"');
$line = array();
foreach($lineArray as $key=>$value) {
	$line[$key] = $value;
}
$values = array();
$valuesArray = array();
foreach ($outputFieldNames as $key=>$value) {
	// Map one input value to its proper output field
	if (isset($fieldNames[$value]) && isset($line[$fieldNames[$value]])) {
		$values[$key] = $line[$fieldNames[$value]];
	}
}
$url = $line[$fieldNames['url']];

// Ensure we do this only for log entries that are related to a URL
if (!isset($url) || (strlen($url) == 0)) {
	// Skip this line because it's not URL-related
	// CyberSpark logs contain many entries that are just sniffer progress or errors.
	exit;
}
			
// Get a value for URL_ID from locally-saved table
// Because log files are very repetitive, we have one most of the time.
$URL_HASH = md5($url);
$URL_ID = 0;
$LOG_ID = 0;

// Get a value for URL_ID by checking first against `urls` table.
// If this URL is in the table, we get the ID. If not in the table, we insert it.
// Look for existing entry in `urls` table
$query = "SELECT `ID` FROM `urls` WHERE `URL_HASH`=?";
$stmt =  $mysqli->stmt_init();
$stmt->prepare($query);
$stmt->bind_param('s', $URL_HASH);
$result = $stmt->execute();
if ($result === TRUE) {
	if ($stmt->errno) {
// >>> bogus
		echo "Error [alert#1] number ".$stmt->errno." <br/>\r\n";
		echo "Error [alert#1] message ".$stmt->error." <br/>\r\n";
	}
	$URL_ID = 0;
	$stmt->bind_result($URL_ID) ;
	$result = $stmt->fetch() ; 	// We assume only one result and fetch only once
	if ($result === TRUE && ($URL_ID > 0)) {
		// It is in the db table
	}
	else {
		if ($stmt->errno) {
// >>> bogus
			echo "Error [alert#2] number ".$stmt->errno." <br/>\r\n";
			echo "Error [alert#2] message ".$stmt->error." <br/>\r\n";
		}
		else {
			// Need to insert the URL into the table	
			$stmt->close();
			$query = 'INSERT INTO `urls` (`URL_HASH`, `url`) VALUES (?,?)';
			$stmt =  $mysqli->stmt_init();
			$stmt->prepare($query);
			$stmt->bind_param('ss', $URL_HASH, $url);
			$result = $stmt->execute();
			$result = $stmt->fetch() ; 
			$URL_ID = $stmt->insert_id;
			// Cache for later log entries
			$URL_HASH = $URL_ID;
		}
	}
}
else {
	// Failure conditions here
}
	
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Process and save one log entry

// INSERT the new data into the `logs` table
if ($URL_ID > 0) {
	$query = 'INSERT INTO `logs` (`URL_HASH`,';
	foreach ($outputFieldNames as $key=>$field) {
		$query .= "`".$field."`,";
	}
	$query = substr($query, 0, strlen($query)-1);
	$query .= ') VALUES ("'.$URL_HASH.'",';	
	$j = count($outputFieldNames)-1;
	for ($i=0; $i<$j; $i++) {
			$query .= '?,';
	}
	$query .= '?)';
	$stmt =  $mysqli->stmt_init();
	$stmt->prepare($query);
	foreach ($outputFieldNames as $key=>$field) {
		if (isset($values[$key])) {
			$valuesArray[] = $values[$key];
		}
		else {
			$valuesArray[] = 0;
		}
	}
	$stmt->bind_param($outputFieldTypes, $valuesArray[0], $valuesArray[1],$valuesArray[2],$valuesArray[3],$valuesArray[4],$valuesArray[5],$valuesArray[6],$valuesArray[7],$valuesArray[8],$valuesArray[9],$URL_ID,$valuesArray[11],$valuesArray[12],$valuesArray[13],$valuesArray[14],$valuesArray[15],$valuesArray[16],$valuesArray[17],$valuesArray[18]);
    $result = $stmt->execute();
	if ($stmt->errno) {	
		if ($stmt->errno == MYSQL_ERROR_DUPLICATE) {
// >>> bogus
			echo "Duplicate log entry <br/>\r\n";
		}
		else {
// >>> bogus
			echo "Error number from INSERT INTO `logs` ".$stmt->errno." <br/>\r\n";
			echo "Error message from INSERT INTO `logs` ".$stmt->error." <br/>\r\n";
		}
	}
	else {
		$LOG_ID = $stmt->insert_id;
	}
	$stmt->close();
}

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Save long message (from one log line) in separate table
if (SAVE_MESSAGES && $LOG_ID) { 
	if (isset($fieldNames['full_message']) && isset($line[$fieldNames['full_message']]) && (strlen($line[$fieldNames['full_message']])>0)) {
		$query = 'INSERT INTO `messages` (`ID`, `message`) VALUES (?,?)';
		$stmt =  $mysqli->stmt_init();
		$stmt->prepare($query);
		$stmt->bind_param('is', $LOG_ID, $line[$fieldNames['full_message']]);
		$result = $stmt->execute();
		if ($stmt->errno && ($stmt->errno != MYSQL_ERROR_DUPLICATE)) {
			echo "Error number from INSERT INTO `messages` ".$stmt->errno." <br/>\r\n";
			echo "Error message from INSERT INTO `messages` ".$stmt->error." <br/>\r\n";
		}
		$stmt->close();
	}
}

if (defined('DEBUG') && DEBUG) {
	// >>> For debugging
	// >>> Write the incoming data to a file
	//////////////////////////////////
	//////////////////////////////////
	$fn = '/var/www/slice/analysis/_cs.log';
	clearstatcache(false, $fn);
	$length = filesize($fn);
	$f = fopen($fn, 'a');
	if (($length == 0) && isset($_POST['header'])) {
		fwrite($f, (trim($_POST['header'])."\n"));
	}
	if (isset($_POST['log'])) {
		fwrite($f, (trim($_POST['log'])."\n"));
	}
	$contents = ob_get_flush();
	if (strlen($contents) > 0) {
		fwrite($f, (trim($contents)."\n"));
	}
	fclose($f);
	//////////////////////////////////
	//////////////////////////////////
}
?>