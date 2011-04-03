<?php

/**
	Spider the site starting in a base directory.
	Contact CyberSpark for more information -> http://cyberspark.net/webmasters
	
	This is a standalone, not to be confused with the Cloudkick 'agent' plugin 
	  version, which has some similar functionality.
	  
	/help
	  Describes what options are available.
	/path
	  Reports the filesystem path to this script. Does nothing else.
	/report
	/report=n
	  Spiders the site and builds a baseline set of hashes or lengths.  Does nothing active.
	  If "n" (a number) is present the site is spidered only to this maximum depth.
	  It's best to start with 1 or 2 so as not to overburden your server.
	/report=n&base=xxxxxxx
	  Prepares a report using "xxxxxxx" as the base subdirectory and a depth of "n"
	/remove
	/remove=n
	/repair
	/repair=n
	  Removes whatever is in /cyberspark/removeme.txt wherever it may be found in
	  any PHP file in the directory or subdirectories.  Note that the extention on
	  the file containing the content TO REMOVE is "txt" and that only files with
	  the extention "php" will actually be changed.
	  If "n" (a number) is present the site is spidered only to this maximum depth.
	/remove=n&base=xxxxxxx
	/repair=n&base=xxxxxxx
	  Performs a repair using "xxxxxxx" as the base subdirectory and a depth of "n"
**/

DEFINE('SPIDERFILE',"spiderlength.ds");		// name of file to which data will be marshalled
DEFINE('STOREFILE', "datastore.ds");		// name of file to which data will be marshalled
DEFINE('CYBERDIR',"cyberspark/");			// MUST have ending slach

$depth      = 0;			// current spidering depth (recursive calls)
$maxDepth   = 50;			// number of levels 'deep' to spider
$results    = array();
$status     = array();
$store      = array();
$newFiles   = 0;			// number files found on this scan that weren't present previous time
$newSizes   = 0;			// number of files that have changed size
$newSuspect = 0;			// number of files containing suspect functions like 'eval()'
$removals   = 0;			// number of files from which the bad code has been removed
$totalFiles = 0;			// number of files seen
$phpFiles   = 0;			// number of PHP files examined
$path       = '';
$base       = '';
$removeMe   = '';			// the string we're searching for - usually is injected PHP code
$repair     = false;
$report     = false;
$views      = 0;
//$wrap       = 0;
//$wrapAt     = 250;
$maxFileSize= 2000000;		// maximum file size that we will open and inspect
$maxDataSize= 14000000;		// maximum serialized data file size
$exclude    = array();		// strings that cause file/directory to be ignored
$wordpress  = array('w3tc','cache');	// directories or files PRESENCE to ignore for WordPress
$checkSignatures = array (
	'eval('=>'[PHP/javascript]',
	'gzinflate('=>'[PHP]',
	'base64_decode'=>'[PHP]',
	'document.write'=>'[javascript]',
	'unescape'=>'[javascript]'
);


if(!function_exists("stripos")){
    function stripos(  $str, $needle, $offset = 0  ){
        return strpos(  strtolower( $str ), strtolower( $needle ), $offset  );
    }/* endfunction stripos */
}/* endfunction exists stripos */

if(!function_exists("strripos")){
    function strripos(  $haystack, $needle, $offset = 0  ) {
        if(  !is_string( $needle )  )$needle = chr(  intval( $needle )  );
        if(  $offset < 0  ){
            $temp_cut = strrev(  substr( $haystack, 0, abs($offset) )  );
        }
        else{
            $temp_cut = strrev(    substr(   $haystack, 0, max(  ( strlen($haystack) - $offset ), 0  )   )    );
        }
        if(   (  $found = stripos( $temp_cut, strrev($needle) )  ) === FALSE   )return FALSE;
        $pos = (   strlen(  $haystack  ) - (  $found + $offset + strlen( $needle )  )   );
        return $pos;
    }/* endfunction strripos */
}/* endfunction exists strripos */  

// Always exclude self from checking for malstrings
if (($i = strripos($_SERVER['PHP_SELF'], '/')) >= 0) {
	$exclude[] = substr($_SERVER['PHP_SELF'], $i+1);
}
echo "Self: $exclude[0]\r\n";

function readCode($path) {
	global $removeMe;
	global $maxFileSize;
	
	if($readHandle = fopen("$path"."cyberspark/removeme.txt","r")) {
		while (!feof($readHandle)) {
			$removeMe = fread($readHandle, $maxFileSize);
		}
		fclose($readHandle);
	}
}

function readData($path, $base) {
	global $fileHandle;
	global $status;
	global $maxDataSize;
	global $store;
	
	if (isset($base) && (strlen($base)>0)) {
		$cleanBase = str_replace('/','-',$base);
	}
	else {
		$cleanBase = '';
	}

	if($fileHandle = @fopen("$path".CYBERDIR.$cleanBase.SPIDERFILE,"r+")) {
		while (!feof($fileHandle)) {
			$status = unserialize(fread($fileHandle, $maxDataSize));	
		}
		fclose($fileHandle);
	}
	if($fileHandle = @fopen("$path".CYBERDIR.$cleanBase.STOREFILE,"r+")) {
		while (!feof($fileHandle)) {
			$store = unserialize(fread($fileHandle, $maxDataSize));	
		}
		fclose($fileHandle);
	}
}

function writeData($path, $base) {
	global $results;
	global $fileHandle;
	global $store;

	if (isset($base) && (strlen($base)>0)) {
		$cleanBase = str_replace('/','-',$base);
	}
	else {
		$cleanBase = '';
	}

	if($fileHandle = fopen("$path".CYBERDIR.$cleanBase.SPIDERFILE,"w+")) {
		rewind($fileHandle);
		fwrite($fileHandle, serialize($results));
		fclose($fileHandle);
	}
	if($fileHandle = fopen("$path".CYBERDIR.$cleanBase.STOREFILE,"w+")) {
		rewind($fileHandle);
		fwrite($fileHandle, serialize($store));
		fclose($fileHandle);
	}
}

function paramValue($paramName) {
	if (isset($_GET[$paramName]))  {
			if (($md = intval($_GET[$paramName])) > 0)
				return $md;
	}
	if (isset($_POST[$paramName]))  {
			if (($md = intval($_POST[$paramName])) > 0)
				return $md;
	}
	// No parameter, return "0" which is "failure"
	return 0;
}

function nValue() {
	// Several options allow "=n" to specify the spidering depth.  Such as:
	//    cyberspark-utility.php?report=3
	// This function looks for the "n" (which means really the value of the
	//   parameter "repair" "remove" or "report") and sets $maxDepth accordingly.
	// $maxDepth remains untouched if there is no "=n"

	global $maxDepth;
	
	if (($md = paramValue('report')) > 0) {
		return $md;
	}
	if (($md = paramValue('remove')) > 0) {
		return $md;
	}
	if (($md = paramValue('repair')) > 0) {
		return $md;
	}
}

function stripos_array($haystack, $needleArray) {
	foreach ($needleArray as $needle) {
		if (stripos($haystack, $needle) !== false) {
			return true;
		}
	}
	return false;
}

function removeCode($baseDirectory, $maxDepth)
{
    global $depth;		// current depth of spidering
	global $removeMe;
	global $removals;
	global $views;
	global $totalFiles;
//	global $wrap;
//	global $wrapAt;
	global $phpFiles;
	global $maxFileSize;
	global $exclude;
	
	if (strlen($removeMe) == 0) {
		echo "There is nothing specified in the file for removal.<br>\r\n";
		return;
	}
	
	// Be sure we're working with a directory
	if (is_dir($baseDirectory) && ($maxDepth>$depth)) {
//		$wrap = $wrap + 1;
//		if ($wrap >= $wrapAt) {
//			echo "<br>\r\n";
//			$wrap = 1;
//		}
//		echo ".";
			$depth++;
			$dirContents = dir($baseDirectory);
			// Run through this directory
			while (($entry = $dirContents->read()) !== false) {
	 			// Next entry in the directory
				$thisEntry = $baseDirectory.$entry;

				// Check whether this file or directory is to be excluded from scanning or repairing
				if ((count($exclude) > 0) && stripos_array($thisEntry, $exclude)) {
					echoAndLog ("Excluding: $thisEntry ");
					continue;
				}

				if ((strcmp('.',$entry)<>0) && (strcmp('..',$entry)<>0) && is_dir($thisEntry)) {
					// Next entry is a directory, dive into it
					removeCode($thisEntry."/", $maxDepth);
				}
				else if (is_link($thisEntry)) {
					// Skip 'link' (not directory, not file) avoids recursion
				}
				else if (is_file($thisEntry)) {
					$totalFiles++;
					// It's a file - check for proper type
					$len = strlen($thisEntry);
					if((($len > 4) and (stripos($thisEntry, ".php", $len-4) == ($len-4))) || (($len > 5) and (stripos($thisEntry, ".html", $len-5) == ($len-5))) || (($len > 4) and (stripos($thisEntry, ".htm", $len-4) == ($len-4))) || (($len > 3) and (stripos($thisEntry, ".js", $len-3) == ($len-3)))) {
						// Filename ends with ".php" or ".PHP" (or ".html" or ".htm" or ".js" so check it
						$phpFiles++;
						if($modHandle = fopen($thisEntry, "r")) {
							$views = $views + 1;
							$contents = fread($modHandle, $maxFileSize);
							if (!feof($modHandle)) {
								echo "<br>\nWARNING: This file was too big to process and you must fix it by hand: $thisEntry ";
								fclose($modHandle);
							}
							else {
								// Make a replacement
								fclose($modHandle);
								$ocLen = strlen($contents);
								$newContents = str_replace($removeMe, "", $contents);
								if (strlen($newContents) != $ocLen) {
									// It was found and "deleted" so rewrite the original file
									echo "<br>\nWARNING: This file was infected: $thisEntry ";
									$removals = $removals + 1;								
									// >>>
									if($writeHandle = fopen($thisEntry, "w+")) {
										rewind($writeHandle);
										fwrite($writeHandle, $newContents);
										fclose($writeHandle);
										echo "<br>\n&nbsp;The infection was removed.";
									}
								}
							}
						}
						else {
							echo "<br>\nThis file could not be opened: $thisEntry ";
						}
					
					}
					else {
						// Skip
					}
				}
				// Otherwise ignore   ("." and ".." for instance)
			}
			$depth--;
		}
}  

function spiderThis($baseDirectory, $maxDepth)
{
    global $depth;		// current depth of spidering
    global $results;	// current 'signatures' of files
    global $status;		// previous 'signatures' of files
    global $newFiles;	// number of new files found during this scan
    global $newSizes;	// number of files that changed size
//    global $wrap;
//    global $wrapAt;
    global $newSuspect;	// number of files containing 'suspect' PHP functions eval() base64_decode() etc.
    global $maxFileSize;
    global $phpFiles;
    global $totalFiles;
    global $checkSignatures;
	global $exclude;
    
	// Be sure we're working with a directory
	if (is_dir($baseDirectory) && ($maxDepth>$depth)) {
//		$wrap = $wrap + 1;
//		if ($wrap >= $wrapAt) {
//			echo "<br>\r\n";
//			$wrap = 1;
//		}
//		echo ".";
			$depth++;
			$dirContents = dir($baseDirectory);
			// Run through this directory
			while (($entry = $dirContents->read()) !== false) {
				// Next entry in the directory
				$thisEntry = $baseDirectory.$entry;

				// Check whether this file or directory is to be excluded from scanning
				if ((count($exclude) > 0) && stripos_array($thisEntry, $exclude)) {
					echo ("<br>\nExcluding: $thisEntry \r\n");
					continue;
				}

				// Look for '.' or '..' and ignore these entries
				if ((strcmp('.',$entry)<>0) && (strcmp('..',$entry)<>0) && is_dir($thisEntry)) {
					// Next entry is a directory, dive into it
					spiderThis($thisEntry."/", $maxDepth);
				}
				else if (is_link($thisEntry)) {
					// Skip a 'link' (not directory, not file) avoids recursion, but might
					// miss something that you want to examine. You can always set up separate
					// scan that uses 'base=' to target the actual directory you want to examine.
					// (This also means you can't scan outside the web space. Guess you could
					//  regard this as a "feature.")
				}
				else if (is_file($thisEntry)) {
					// It's a file
					$stat = stat($thisEntry);
					$totalFiles++;

// MD5: use this to record md5 hashes of files rather than lengths
//   but this will be much more time-consuming than just looking at lengths.
// $filemd5s[] = md5_file($thisentry);

					// Record file lengths
					$fileSize = $stat['size'];
					$results[$thisEntry] = $fileSize;
					if ($status[$thisEntry] <> $fileSize) {
						if ($status[$thisEntry] == 0) {
							echo "<br>\nNew file: ".$status[$thisEntry]." -> [".$fileSize."] $thisEntry ";
							$newFiles++;
						}
						else {
							$len = strlen($thisEntry);
							if(($len > 3) and !(strripos($thisEntry, "log") == ($len-3)) and !(strripos($thisEntry, SPIDERFILE) == ($len-strlen(SPIDERFILE))) and !(strripos($thisEntry, DATAFILE) == ($len-strlen(DATAFILE)))) {
								// Note: Ignore files ending in "log"
								// Note: Ignore files ending with the name of our data file
								// Otherwise, note a changed size
								echo "<br>\nNew size: ".$status[$thisEntry]." -> [".$fileSize."] $thisEntry ";
								$newSizes++;
							}
						}
					}
					
// And scan PHP files for eval and gzinflate and base64
						$len = strlen($thisEntry);
						if(($len > 4) and ((strripos($thisEntry, ".php") == ($len-4))
						|| (strripos($thisEntry, ".htm") == ($len-4))
						|| (strripos($thisEntry, ".html") == ($len-5))
						|| (strripos($thisEntry, ".js") == ($len-3))
						)) {
							$thisFile = fopen($thisEntry,"r");
							$thisContents = fread($thisFile, $maxFileSize);
							fclose($thisFile);
							$phpFiles++;
							if (strlen($thisContents) > 0) {
								// check for PHP and javascript active code
								foreach ($checkSignatures as $key => $value) {
									if (stripos($thisContents, $key) !== false) {
										echo ("<br>\nFound '$key' $value: -> $thisEntry");
										$newSuspect++;
									}
								}
							}
						}

					// Remove from 'previous status' array.  When we finish, anything left in
					// this array will be a file that has disappeared.
					unset($status[$thisEntry]);
				}
				// Otherwise ignore   ("." and ".." for instance)
			}
			$depth--;
		}
}  





// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// MAIN script - this is executed when the file is invoked by HTTP GET or HTTP PUT

// Get the filesystem path to this file (only the PATH) including an ending "/"
	$targ = strrpos(__FILE__, chr(ord('/')));
	$path = substr(__FILE__, 0, $targ+1);	// including the last 


// '/help'  - - - - - - - - - - - -
if (isset($_GET['help'])) {
	//  /help
	echo "<html>\n<body width=600px align=left>
	/help          <br>
	/path          <br>
	/report        <br>
	/report=n      <br>
	/report=n&base=xxxxxxx      <br>
	  <p style='margin-left:30px;width:600px;'>Produces a report on status of all PHP files.
	  If 'n' (a number) is present the site is spidered only to this maximum depth.
	  (Start by using 1 or 2 for the depth until you know how quickly your server can
	  perform this task.)  </p>
	  <p style='margin-left:30px;width:600px;'>Run several times to establish a baseline, then in the future
	  you can watch for any significant changes.
	  </p>
	  <p style='margin-left:30px;width:600px;'>If base=xxxxxxx is specified then the report starts at
	  directory /xxxxxxx with respect to where the CyberSpark PHP is located
	  </p>
	/remove        <br>\n
	/remove=n      <br>\n
	/remove=n&base=xxxxxxx      <br>
	/repair=b&nbsp;&nbsp;&nbsp;&nbsp;(same as -remove-)<br>
	  <p style='margin-left:30px;width:600px;'>Repairs PHP files by removing code found in file 
	  'removeme.txt' within '/cyperspark' directory.
	  If 'n' (a number) is present the site is spidered
	  only to this maximum depth.
	  </p>
	  <p style='margin-left:30px;width:600px;'>If base=xxxxxxx is specified then the report starts at
	  directory /xxxxxxx with respect to where the CyberSpark PHP is located
	  </p>
	</body>\n</html>\n";
	return;
}

// '/path' - - - - - - - - - - - - -
if (isset($_GET['path'])) {
	//  /path
	echo "<html>\r\n<body>\r\n<div align='left'> $path </div>\r\n</body>\r\n</html>\r\n";
	return;
}

// '/remove'  '/repair' detection  - -
if (isset($_GET['remove']) or isset($_POST['remove']) or isset($_GET['repair']) or isset($_POST['repair'])) {
	// Set flag for actions to perform
	$repair = true;
}

// '/report'  detection  - - - - - - -
if (isset($_GET['report']) or isset($_POST['report'])) {
	// Set flag for actions to perform
	$report = true;
}

// '=n'  detection+processing  - - - -
	$maxDepth = nValue();

// 'base=xxxxxxx' detection+processing 
if ($report or $repair) {
	if (isset($_GET['base'])) {
		$base = $_GET['base'] . '/';
	}
	if (isset($_POST['base'])) {
		$base = $_POST['base'] . '/';
	}
	if (strpos($base, '..') !== false) {
		// Don't permit /../ anywhere in the base string
		// Just fall back to the directory containing this script
		$base = '';
	}
}

// header for reports  - - - - - - - -
if ($report or $repair) {
	//  /report   or /repair
	$fullPath = $path . $base;
	echo "<html>
	<body width='600px'> 
	<p style='margin-left:30px;width:570px;'>
	CyberSpark local agent report<br>
	Base directory is $fullPath <br>
	Spidering depth will be $maxDepth <br>
	Any changes reported below are 'since the last time this script was run.'<br>
	Do not stop this script or leave this page until it finishes.
	</p>
";
	// The html is closed off later on after the scan has been completed
}

// read previous file status info  - - - -
readData($path, $base);

// the bulk of processing HERE   - - - - -
if ($repair) {
	readCode($path);
	removeCode($path . $base, $maxDepth);	// spider with a maximum depth Example: "1" limits to top directory
	echo "<br>\r\n";
}
else if ($report) {
	spiderThis($path . $base, $maxDepth);	// spider with a maximum depth Example: "1" limits to top directory
	echo "<br>\r\n";
}

// reporting out - - - - - - - - - - - - -
if ($repair) {
	echo "\r\n<br><br>- - - - - - - - - - - - - - - - -<br>\r\n";
	echo "Summary<br><br>\r\n";
	echo "<br>\r\nTotal files: $totalFiles<br>\r\nPHP, JS or HTML files examined: $views<br>\r\nFiles repaired: $removals<br><br><br>\r\n";
}
else if ($report) {
	echo "\r\n<br><br>- - - - - - - - - - - - - - - - -<br>\n";
	echo "Summary<br>\n";
	if ($phpFiles > 0) {
		echo "<br>\r\nPHP files examined: $phpFiles<br>\n";
		if (isset($store['phpfiles']) && ($phpFiles != $store['phpfiles'])) {
			echo "-> Warning: The number of PHP/HTML/JS files has changed from ".$store['phpfiles']." to ".$phpFiles."!<br>\n";
		}
		echo "PHP files with suspicious code: $newSuspect<br>\n";
		if (isset($store['suspectfiles']) && ($newSuspect != $store['suspectfiles'])) {
			echo "-> Critical: The number of suspicious files has changed from ".$store['suspectfiles']." to ".$newSuspect."!<br>\n";
		}
	}
	if (sizeof($status) > 0) {
		echo "<br>\r\nFiles gone: <br>\n";
		foreach ($status as $key=>$value) {
			echo "[$value] $key<br>\n";
		}
	}
}

$store['phpfiles'] = $phpFiles;
$store['suspectfiles'] = $newSuspect;

//  /report
if ($report) {
	echo "New files:     $newFiles<br>\n";
	echo "Changed sizes: $newSizes<br>\n";
	echo "Files gone:    ".sizeof($status)."<br>\n";
	
	if (function_exists("sys_getloadavg")) {
		$loads = sys_getloadavg();
		echo "Load:          ".$loads[0]." ".$loads[1]." ".$loads[2];
	}
}

// closing the HTML - - - - - - - - - - - -
echo "</div>\r\n</body>\r\n</html>\r\n";

if ($report) {
	writeData($path, $base);
}

?>
