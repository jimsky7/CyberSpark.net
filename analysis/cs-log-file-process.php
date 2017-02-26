<?php
/**** cs-log-file-process.php

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

	This script reads a CyberSpark log file into MySQL for analysis by
	CyberSpark software.
	
	You have to make a web <FORM> to target this script. Filename must include full path.
	
	The log file contains these fields (read down the columns):
		milliseconds	crashes			url	 			hour
		date			http_ms			result_code		minute
		host			length			year			second
		thread			md5				month			APIusage
		tick			condition		day				full_message
	The field 'url' is forked into additional fields in the database table `logs`:
		URL_HASH		URL_ID									
	The field 'full_message' is diverted into the `messages` table.			

The log columns may actually be in any order. We read the first line, figure out the field
names, then adjust on the fly if they're not in the order we listed them above.

	These fields are built in the `logs` table (every record is a fixed length):
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
	
****/
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Setup

require_once('cs-log-config.php');
require_once('cs-log-functions.php');
require_once('cs-log-pw.php');

define ('ECHO_SAMPLE', false);			// echo one sample SQL
define ('LINE_LIMIT', 0);				// how many log entries to process before exiting (0==unlimited)
define ('DUPLICATE_LIMIT', 0);			// how many duplicate log entries to count before exiting (0=unlimited)
define ('MYSQL_ERROR_DUPLICATE', 1062);	// MySQL error number for "duplicate"

ini_set('auto_detect_line_endings', true);
if (!isset($_POST['FILE_NAME'])) {
	die("No filename from FORM <br/>\r\n");
}
$fileName = $_POST['FILE_NAME'];
	
echo "<html><body><p>&laquo; <a href='$_POST[HTTP_REFERER]'>Back</a></p>";

// Check the "filename" to see whether it might be a complete directory
if (is_dir($fileName)) {
	$base = trim($fileName, '/');	// trim leading and trailing to even them out
	$base = "/$base/";			// put a slash on both ends, giving us a base
	$fileArray = scandir($base, 0);	// sorted in ascending order	
	echo "This directory will be scanned for files and each one will be processed <br/>\r\n";
	echo $base."<br/>\r\n";
}
else {
	if (is_file($base.$fileName)) {
		$fileArray = array($fileName);
		$base = dirname($fileName);
		echo "A single file will be read  $base$fileArray[0] <br/>\r\n";
	}
	else {
		die("Could not interpret $fileName as either a file or a directory <br/>\r\n");
	}
}

// Desired output field names in the order you want them sent to the database
// You don't have to emit everything that was read from the log
$outputFieldNames = array (0=>'milliseconds', 1=>'date', 2=>'host', 3=>'thread',
	4=>'tick', 5=>'crashes', 6=>'http_ms', 7=>'length', 8=>'md5',
	9=>'condition', 10=>'URL_ID', 11=>'result_code', 12=>'year', 13=>'month', 14=>'day',
	15=>'hour', 16=>'minute', 17=>'second', 18=>'APIusage'
);
// Use TRUE or FALSE for each depending on whether you want a field quoted in the output
$outputFieldQuote = array (0=>false, 1=>true, 2=>true, 3=>true,
	4=>false, 5=>false, 6=>false, 7=>false, 8=>true,
	9=>true, 10=>true, 11=>false, 12=>false, 13=>false, 14=>false,
	15=>false, 16=>false, 17=>false, 18=>false
);
$outputFieldTypes = '';
foreach ($outputFieldQuote as $key=>$value) {
	$outputFieldTypes .= ($value?'s':'i');
}

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Open mysqli, check for connection errors

$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);

if ($mysqli != null) {
	if ($mysqli->connect_errno) {
		echo "'mysqli' error $mysqli->connect_errno.<br/>\r\n";
		echo "$mysqli->connect_error.<br/>\r\n";
		exit;
	}
	echo "Opened a connection to MySQL<br/>\r\n";
}

foreach ($fileArray as $fn) {
	if (is_file($base.$fn)) {
		echo "Checking $base$fn <br/>\r\n";
		
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
		// Check file names in the database to see whether we've read this file already
		// This particularly helps when we are digesting a directory and need to skip whole files
		$query = "SELECT `ID` FROM `files` WHERE `name`=?";
		$stmt =  $mysqli->stmt_init();
		$stmt->prepare($query);
		$fns = $base.$fn;
		$stmt->bind_param('s', $fns);
		$result = $stmt->execute();
		if ($result === TRUE) {
			if ($stmt->errno) {
				echo "Error [alert#4] number ".$stmt->errno." <br/>\r\n";
				echo "Error [alert#4] message ".$stmt->error." <br/>\r\n";
			}
			$ID = 0;
			$stmt->bind_result($ID) ;
			$result = $stmt->fetch() ; 	// We assume only one result and fetch only once
			if ($ID > 0) {
				echo "-----------------------------------------------<br/>\r\n";
				echo "Previously processed <span style='color:red;'>$base$fn</span> (skipping now) <br/>\r\n";
				continue;
			}
		}
		$stmt->close();
		echo "Opening $base$fn <br/>\r\n";
				
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
		$fh = fopen($base.$fn, 'r');

		if ($fh === FALSE) {
			die("Could not open $base$fn <br/>\r\n");
		}
		echo "-----------------------------------------------<br/>\r\n";
		echo "Reading $base$fn <br/>\r\n";
		// Process first line of log file, which contains field names
		$line = fgetcsv($fh);
		if ($line === FALSE) {
			die("Something is wrong with $base$fn (skipped this file) <br/>\r\n");
		}
		$fieldNames = array();
		$sampleEchoed = false;
		$linesProcessed = 0;
		$duplicates = 0;
		$saveMessaged = false;
		$messageCount = 0;
		$IDforHASH = array();	// key is URL_HASH, value is URL_ID corresponding to row number in `urls` table

		// Capture field names, in order, from the log file (note we preserve case)
		foreach ($line as $field) {
			$fieldNames[] = $field;
		}	
		$fieldNames = array_flip($fieldNames);

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Process the lines of a log file.

		while (($line = fgetcsv($fh)) !== false) {
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
				continue;
			}
			
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// For a log entry (line) first determine the URL being referenced and either
// re-use its HASH ID number or create a new one. These go in the `urls` table.

			// Get a value for URL_ID from locally-saved table
			// Because log files are very repetitive, we have one most of the time.
			$URL_HASH = md5($url);
			$URL_ID = 0;
			if (isset($IDforHASH[$URL_HASH])) {
				$URL_ID = $IDforHASH[$URL_HASH];
			}
			else {
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
						echo "Error [alert#1] number ".$stmt->errno." <br/>\r\n";
						echo "Error [alert#1] message ".$stmt->error." <br/>\r\n";
					}
					$URL_ID = 0;
					$stmt->bind_result($URL_ID) ;
					$result = $stmt->fetch() ; 	// We assume only one result and fetch only once
					if ($result === TRUE && ($URL_ID > 0)) {
						$IDforHASH[$URL_HASH] = $URL_ID;
					}
					else {
						if ($stmt->errno) {
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
							$IDforHASH[$URL_HASH] = $URL_ID;
						}
					}
				}
				else {
					// Other failure conditions here
				}
			}
	
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Process and save one log entry

			// INSERT the new data into the `logs` table
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
			$LOG_ID = 0;
			if ($stmt->errno) {	
				if ($stmt->errno == MYSQL_ERROR_DUPLICATE) {
					$duplicates++;
					if (DUPLICATE_LIMIT) {
						echo $stmt->error." <br/>\r\n";
					}
					if (DUPLICATE_LIMIT && ($duplicates >= DUPLICATE_LIMIT)) {
						die("Stopped at your limit of $duplicates duplicate entries <br/>\r\n");
					}
				}
				else {
					echo "Error number from INSERT INTO `logs` ".$stmt->errno." <br/>\r\n";
					echo "Error message from INSERT INTO `logs` ".$stmt->error." <br/>\r\n";
				}
			}
			else {
				$LOG_ID = $stmt->insert_id;
			}
			$stmt->close();
	
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Save long message (from one log line) in separate table

			if (SAVE_MESSAGES && $LOG_ID) { 
				if (isset($fieldNames['full_message']) && isset($line[$fieldNames['full_message']]) && (strlen($line[$fieldNames['full_message']])>0)) {
					if (!$saveMessaged) {
						echo "Saving log messages in a separate table. This has storage consequences. <br/>\r\n";
						$saveMessaged = true;
					}
					$query = 'INSERT INTO `messages` (`ID`, `message`) VALUES (?,?)';
					$stmt =  $mysqli->stmt_init();
					$stmt->prepare($query);
					$stmt->bind_param('is', $LOG_ID, $line[$fieldNames['full_message']]);
					$result = $stmt->execute();
					$messageCount++;
					if ($stmt->errno && ($stmt->errno != MYSQL_ERROR_DUPLICATE)) {
						echo "Error number from INSERT INTO `messages` ".$stmt->errno." <br/>\r\n";
						echo "Error message from INSERT INTO `messages` ".$stmt->error." <br/>\r\n";
					}
					$stmt->close();
				}
			}
	
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Preamble to the concluding report

			if (ECHO_SAMPLE && !$sampleEchoed) {
				echo ">>>>>>>>>>>>>>>>>>>>>>>>> <br/>\r\n";
				echo "Sample (prepared/bound) output: <br/>$query <br/>\r\n";
				$sampleEchoed = true;
			}
			$linesProcessed++;
			if (LINE_LIMIT && ($linesProcessed >= LINE_LIMIT)) {
				die("Stopped at your limit of $linesProcessed lines <br/>\r\n");
				break;
			}
	
	
		} /* while */

		fclose($fh);

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Concluding report for one log file

		echo "Imported $linesProcessed lines from $fn <br/>\r\n";
		echo "$duplicates duplicate records were skipped <br/>\r\n";
		if (SAVE_MESSAGES) {
			echo "Saved $messageCount long/full messages in the database <br/>\r\n";
		}

		$query = 'INSERT INTO `files` (`name`) VALUES (?)';
		$stmt =  $mysqli->stmt_init();
		$stmt->prepare($query);
		$bfs = $base.$fn;
		$stmt->bind_param('s', $bfs);
		$result = $stmt->execute();
		$result = $stmt->fetch() ; 
		$ID = $stmt->insert_id;
		echo "Saved file name to avoid future reprocessing $base$fn <br/>\r\n";
		$stmt->close();

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>

	} /* if is file */
} /* for each file in directory */

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// All done

$mysqli->close();
echo "All done<br/>\r\n";

echo '</body></html>';

?>