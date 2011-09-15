#!/usr/bin/php -q
<?php

/** Version 4.02 on 20110402 **/

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
	/exclude=aaa,bbb,ccc
	/except=aaa,bbb,ccc
	/ignore=aaa,bbb,ccc
	  Causes file(s) or directory(ies) containing strings 'aaa' or 'bbb' or 'ccc' to be ignored
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

	Create a directory /cyberspark within the docroot of the web server
	Make this directory world-writeable (chmod 777    or chmod a+rwx    )
	Within your Apache (or other) web server configuration, add:
		<Directory /PATH_TO_DOCROOT/cyberspark/>
			deny from all
		</Directory>
	So the directory cannot be shown to the outside world.
	Another way to do this is:
		<IfModule mod_alias.c>
			# Never serve anything from /cyberspark subdirectory
    			RedirectMatch 404 ^(.*)cyberspark/(.*)
		</IfModule>
**/

DEFINE('CYBERDIR',"cyberspark/");			// MUST have ending slash
DEFINE('SPIDERFILE',"spiderlength.ds");		// name of file to which data will be marshalled
DEFINE('STOREFILE', "datastore.ds");		// name of file to which data will be marshalled
DEFINE('LOGFILE', "cyberspark.log");		// verbose log file
DEFINE('REMOVEME',"removeme.txt");			
DEFINE('DISKWARNING', 85);					// issue a warning when this percent of filesystem is full
DEFINE('DISKCRITICAL', 95);					// issue a CRITICAL warning when this percent of filesystem is full

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
$maxFileSize= 2000000;		// maximum file size that we will open and inspect
$maxDataSize= 14000000;		// maximum serialized data file size
$logEntry	= '';			// this will be written to a log file
$exclude    = array();		// strings that cause file/directory to be ignored
							// PHP executables will still be examined
$wordpress  = array('w3tc','cache');	// directories or files PRESENCE to ignore for WordPress
$checkSignatures = array (
	'eval('=>'[PHP/javascript]',
	'gzinflate('=>'[PHP]',
	'base64_decode'=>'[PHP]',
	'document.write'=>'[javascript]',
	'unescape'=>'[javascript]'
);

function readCode($path) {
	global $removeMe;
	global $maxFileSize;
	
	if($readHandle = fopen("$path".CYBERDIR.REMOVEME,"r")) {
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
		$cleanBase = str_replace('/','-',$base).'-';  // add '-' for CLI version
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
		$cleanBase = str_replace('/','-',$base).'-';  // add '-' for CLI version
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

function writeLog($message) {
	if($fileHandle = fopen("$path".CYBERDIR.$cleanBase.LOGFILE,"a")) {
		rewind($fileHandle);
		fwrite($fileHandle, $message);
		fclose($fileHandle);
	}
}

function echoAndLog($string) {
	global $logEntry;
	if (isset($string)) {
		echo $string."\n";   // no <br> for CLI version
		$logEntry .= $string."\r\n";
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
	global $phpFiles;
	global $maxFileSize;
	
	if (strlen($removeMe) == 0) {
		echoAndLog ("There is nothing specified in the file for removal.");
		return;
	}
	
	// Be sure we're working with a directory
	if (is_dir($baseDirectory) && ($maxDepth>$depth)) {
		try {
			$depth++;
			$dirContents = dir($baseDirectory);
			// Run through this directory
			while (($entry = $dirContents->read()) !== false) {
				// Get an entry from the directory
				$thisEntry = $baseDirectory.$entry;

				// Check whether this file or directory is to be excluded from scanning or repairing
				if ((count($exclude) > 0) && stripos_array($thisEntry, $exclude)) {
					echoAndLog ("Excluding: $thisEntry ");
					continue;
				}

				// Look for '.' or '..' and ignore these entries
				if ((strcmp('.',$entry)<>0) && (strcmp('..',$entry)<>0) && is_dir($thisEntry)) {
					// Next entry is a directory, dive into it
					removeCode($thisEntry."/", $maxDepth);
				}
				else if (is_link($thisEntry)) {
					// Skip a 'link' (not directory, not file) avoids recursion, but might
					// miss something that you want to examine. You can always set up separate
					// scan that uses 'base=' to target the actual directory you want to examine.
					// (This also means you can't scan outside the web space. Guess you could
					//  regard this as a "feature.")
				}
				else if (is_file($thisEntry)) {
					$totalFiles++;
					// It's a file - check for proper type
					$len = strlen($thisEntry);
					if(($len > 4) and ((strripos($thisEntry, ".php") == ($len-4))
						|| (strripos($thisEntry, ".htm") == ($len-4))
						|| (strripos($thisEntry, ".html") == ($len-5))
						|| (strripos($thisEntry, ".js") == ($len-3))
						)) {
						// Filename ends with ".php" or ".PHP" or "html" or "htm" or "js" so check it
						$phpFiles++;
						if($modHandle = fopen($thisEntry, "r")) {
							$views = $views + 1;
							$contents = fread($modHandle, $maxFileSize);
							if (!feof($modHandle)) {
								echoAndLog ("WARNING: This file was too big to process and you must fix it by hand: $thisEntry ");
								fclose($modHandle);
							}
							else {
								// Make a replacement
								fclose($modHandle);
								$newContents = str_replace($removeMe, "", $contents, $count);
								if ($count > 0) {
									// It was found and "deleted" so rewrite the original file
									echoAndLog ("WARNING: This file was infected: $thisEntry ");
									$removals = $removals + 1;								
									// >>>
									if($writeHandle = fopen($thisEntry, "w+")) {
										rewind($writeHandle);
										fwrite($writeHandle, $newContents);
										fclose($writeHandle);
										echoAndLog ("&nbsp;The infection was removed.");
									}
								}
							}
						}
						else {
							echoAndLog ("This file could not be opened: $thisEntry ");
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
		catch (Exception $x) {
			echoAndLog ("Exception: $x->getMessage()");
		}
	}
}  

function spiderThis($baseDirectory, $maxDepth)
{
    global $depth;		// current depth of spidering
    global $results;	// current 'signatures' of files
    global $status;		// previous 'signatures' of files
    global $newFiles;	// number of new files found during this scan
    global $newSizes;	// number of files that changed size
    global $newSuspect;	// number of files containing 'suspect' PHP functions eval() base64_decode() etc.
    global $maxFileSize;
    global $phpFiles;
    global $totalFiles;
    global $logEntry;
    global $exclude;
    global $checkSignatures;
    
	// Be sure we're working with a directory
	if (is_dir($baseDirectory) && ($maxDepth>$depth)) {
		try {
			$depth++;
			$dirContents = dir($baseDirectory);
			// Run through this directory
			while (($entry = $dirContents->read()) !== false) {
				// Get an entry from the directory
				$thisEntry = $baseDirectory.$entry;
				
				// Check whether this file or directory is to be excluded from scanning
				if ((count($exclude) > 0) && stripos_array($thisEntry, $exclude)) {
					echoAndLog ("Excluding: $thisEntry ");
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
							// New file
							// Report the presence of a new file
							echoAndLog("New file: ".$status[$thisEntry]." -> [".$fileSize."] $thisEntry ");
							$newFiles++;
						}
						else {
							$len = strlen($thisEntry);
							if(($len > 3) and !(strripos($thisEntry, "log") == ($len-3)) and !(strripos($thisEntry, SPIDERFILE) == ($len-strlen(SPIDERFILE))) and !(strripos($thisEntry, DATAFILE) == ($len-strlen(DATAFILE)))) {
								// Note: Ignore files ending in "log"
								// Note: Ignore files ending with the name of our data file
								// Otherwise, note a changed size
								echoAndLog("New size: ".$status[$thisEntry]." -> [$fileSize] $thisEntry ");
								$newSizes++;
							}
						}
					}

					// And scan PHP files for eval and gzinflate and base64
					try {
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
								foreach ($checkSignatures as $signature=>$value) {
									if (stripos($thisContents, $signature) !== false) {
										echoAndLog("Found '$signature' $value: -> $thisEntry");
										$newSuspect++;
									}
								}
							}
						}
					}
					catch (Exception $egbx) {
					}

					// Remove from 'previous status' array.  When we finish, anything left in
					// this array will be a file that has disappeared.
					unset($status[$thisEntry]);
				}
				// Otherwise ignore   ("." and ".." for instance)
			}
			$depth--;
		}
		catch (Exception $x) {
			echoAndLog( "Exception: ".$x->getMessage());
		}
	}
}  


// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// MAIN program - this is executed when the file is executed

// Get the filesystem path to this file (only the PATH) including an ending "/"
$path = substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"

// Disk/filesystem space used
$diskTotalSpace = 0;
$diskUsedSpace = 0;
if ( function_exists('disk_total_space')) {
	$diskTotalSpace = disk_total_space("/");
}
if ( function_exists('disk_free_space')) {
	$diskUsedSpace = disk_free_space("/");
}
if ($diskTotalSpace > 0) {
	// Note: $diskPercentUsed is only defined if total space > 0
	$diskPercentUsed = $diskUsedSpace/$diskTotalSpace * 100;
}

// '/help'  - - - - - - - - - - - -
if ($argv[1] == 'help' or $argv[1] == '--help') {
	//  /help
	echo 'Use a single parameter:
--help
--path 
  To find out the path to the file.
--report
--report n
--report n /xxxxxxx
  To run a report.  
  If "n" is present, spiders (integer) "n" levels deep.
  Default is 50 levels deep if "=n" is not present.
  Start by using 1 or 2 and see how your server performs
    before using a greater depth.
  If /xxxxxxx is present, start from subdirectory xxxxxxx
    relative to where the PHP script is located.
--remove
--remove n
--repair
--repair n
--repair n /xxxxxxx
  To repair PHP files that have been hacked.
  If "n" is present, repairs files (integer) "n" levels deep.
  Default is 50 levels deep if "=n" is not present.
  If /xxxxxxx is present, start from subdirectory xxxxxxx
    relative to where the PHP script is located.
';
	exit;
}

// '/path' - - - - - - - - - - - - -
if ($argv[1] == 'path' or $argv[1] == '--path') {
	//  /path
	echo "$path\n";
	exit;
}


// '/remove'  '/repair' detection  - -
if ($argv[1] == 'remove' or $argv[1] == '--remove' or $argv[1] == 'repair' or $argv[1] == '--repair') {
	// Set flag for actions to perform
	$repair = true;
}

// '/report'  detection  - - - - - - -
if ($argv[1] == 'report' or $argv[1] == '--report') {
	// Set flag for actions to perform
	$report = true;
}

// '=n'  detection+processing  - - - -
try {
	$maxDepth = $argv[2];
}
catch (Exception $mdx) {
}

// 'base=' detection+processing  - - -
try {
	$base = $argv[3] . '/';
	if (strpos($base, '..') !== false) {
		// Don't permit /../ anywhere in the base string
		// Just fall back to the directory containing this script
		$base = '';
	}
}
catch (Exception $mdx) {
}

// header for reports  - - - - - - - -
if ($report or $repair) {
	//  /report
	$cleanBase = $path . $base;
	echo "
CyberSpark local agent report
Base directory is $cleanBase
Spidering depth is $maxDepth
Any changes reported below are 'since the last time this script was run.'
Do not stop this script or leave this page until it finishes.\n\n";
}

// read previous file status info  - - - -
readData($path, $base);

$logEntry .= "///////////////////////////////////\r\n".date('r')."\r\n";
$logEntry .= "Base $fullPath\n\rDepth $maxDepth\r\n";

// the bulk of processing HERE   - - - - -
if ($repair) {
	readCode($path);
	removeCode($path . $base, $maxDepth);	// spider with a maximum depth Example: "1" limits to top directory
	echo "\n";
}
else if ($report) {
	spiderThis($path . $base, $maxDepth);	// spider with a maximum depth Example: "1" limits to top directory
	echo "\n";
}

// reporting out - - - - - - - - - - - - -
if ($repair) {
	echo "\n\n- - - - - - - - - - - - - - - - -\n";
	echoAndLog ("Summary report");
	echoAndLog ("Total files: $totalFiles");
	echoAndLog ("PHP files examined: $views");
	echoAndLog ("Files repaired: $removals");
}
else if ($report) {
	echo "\n\n- - - - - - - - - - - - - - - - -\n";
	echoAndLog ("Summary report");
	echoAndLog ("Total files: $totalFiles");
	if ($phpFiles > 0) {
		echo "\n";
		echoAndLog ("PHP files examined: $phpFiles");
		if (isset($store['phpfiles']) && ($phpFiles != $store['phpfiles'])) {
			echoAndLog ("-> Warning: The number of PHP files has changed from ".$store['phpfiles']." to $phpFiles");
		}
		echoAndLog ("PHP files with suspicious code: $newSuspect");
		if (isset($store['suspectfiles']) && ($newSuspect != $store['suspectfiles'])) {
			echoAndLog ("-> Critical: The number of suspicious files has changed from ".$store['suspectfiles']." to $newSuspect");
		}
	}
	// Look for files that are gone but are to be excluded from analysis
	// (Any file still left in $status at this point was not seen during directory
	//   traversal, and thus has gone away.)
	if ((sizeof($status) > 0) && (sizeof($exclude) > 0)) {
		foreach ($status as $thisEntry=>$value) {
			foreach ($exclude as $excludeThis) {
				if (stripos($thisEntry, $excludeThis) !== false) {
					// This file's name or path is marked to be excluded from analysis
					// so ignore that it's gone
					unset($status[$thisEntry]);
					break;
				}
			}
		}	
	}
	// Report the names of files that are gone
	if (sizeof($status) > 0) {
		echo "<br>\r\n";
		echoAndLog ("Files gone: ");
		foreach ($status as $key=>$value) {
			echoAndLog ("[$value] $key");
		}
	}
}

$store['phpfiles'] = $phpFiles;
$store['suspectfiles'] = $newSuspect;

//  /report
if ($report) {
	echoAndLog ("New files:     $newFiles");
	echoAndLog ("Changed sizes: $newSizes");
	echoAndLog ("Files gone:    ".sizeof($status));
	
	// Report on system load
	$loads = sys_getloadavg();
	echoAndLog ("Load:          ".$loads[0]." ".$loads[1]." ".$loads[2]);
	
	// Report on how full the disk/filesystem is
	if (isset($diskPercentUsed)) {
		if ($diskPercentUsed > DISKCRITICAL) {
		;
			echoAndLog (sprintf('Disk critical: %000d%% used of %000d GB total', $diskPercentUsed, $diskTotalSpace/1000000000));
		}
		else if ($diskPercentUsed > DISKWARNING) {
			echoAndLog (sprintf('Disk warning: %000d%% used of %000d GB total', $diskPercentUsed, $diskTotalSpace/1000000000));
		}
		else {
			echoAndLog (sprintf('Disk: %000d%% used of %000d GB total', $diskPercentUsed, $diskTotalSpace/1000000000));
		}
	}
}

if ($report) {
	writeData($path, $base);
	writeLog ($logEntry);
}

?>
