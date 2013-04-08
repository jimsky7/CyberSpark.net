<?php

/** Version 4.06 on 2012-09-30 **/

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
	  the base is relative to the directory containing this file and must start and end with '/'
	/exclude=aaa,bbb,ccc
	/except=aaa,bbb,ccc
	/ignore=aaa,bbb,ccc
	  Causes file(s) or directory(ies) containing strings 'aaa' or 'bbb' or 'ccc' to be ignored
	/wordpress
	  Causes certain subdirectories/files to be ignored - good for wordpress installations
	/disk=dev
	  Reports space on specific device/volume   "/dev"  whatever you specify
	/disk=none
	  Turns off the disk capacity and space checking.  (actually, just makes it fail)
	/cpu=n
	  Artificially waits 'n' seconds of wait time to measure overall CPU load 
	REPAIR CAPABILITIES ARE NOT PRESENT IN THIS SCRIPT.
	
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
DEFINE('REPORTFILE', "cyberspark.html");	// most recent single report

$depth      = 0;			// current spidering depth (recursive calls)
$maxDepth   = 50;			// number of levels 'deep' to spider
$results    = array();
$status     = array();
$store      = array();
$newFiles   = 0;			// number files found on this scan that weren't present previous time
$newSizes   = 0;			// number of files that have changed size
$newSuspect = 0;			// number of files containing suspect functions like 'eval()'
$totalFiles = 0;			// number of files seen
$phpFiles   = 0;			// number of PHP files examined
$path       = '';
$base       = '';
$fsPath     = '/';			// default filesystem base for figuring disk size
$report     = false;
$views      = 0;
$maxFileSize= 2000000;		// maximum file size that we will open and inspect
$maxDataSize= 14000000;		// maximum serialized data file size
$logEntry	= '';			// this will be written to a log file
$exclude    = array();		// strings that cause file/directory to be ignored
							// PHP executables will still be examined
$wordpress  = array('w3tc','cache');	// directories or files PRESENCE to ignore for WordPress
$myName     = '';

$checkSignatures = array (
	'eval('=>'[PHP/javascript]',
	'gzinflate('=>'[PHP]',
	'base64_decode'=>'[PHP]',
	'document.write'=>'[javascript]',
	'unescape'=>'[javascript]',
// Some specific injections that we've seen recently
	'geb7'            =>'[ALERT:javascript (geb7) <span style="color:red;">Probably compromised</span>]',
	'qbaa6fb797447'   =>'[ALERT:javascript (qbaa6fb797447) <span style="color:red;">Probably compromised</span>]',
	'alisoe'          =>'[ALERT:javascript injection (alisoe) <span style="color:red;">Probably compromised</span>]',
	'lisisa'          =>'[ALERT:javascript injection (lisisa) <span style="color:red;">Probably compromised</span>]',
	'12thplayer'      =>'[ALERT:javascript injection (12thplayer) <span style="color:red;">Probably compromised</span>]',
	'pentestmonkey'   =>'[ALERT:PHP-reverse-shell? (pentestmonkey) <span style="color:red;">Probably compromised</span>]',
	'_0xdc8d'         =>'[ALERT:javascript injection (_0xdc8d) <span style="color:red;">Probably compromised</span>]',
	'exploit-db.com'  =>'[ALERT:PHP-reverse-shell? (exploit-db.com) <span style="color:red;">Probably compromised</span>]',
	'$__name'         =>'[ALERT:javascript ($__name) <span style="color:red;">Probably compromised</span>]',
);

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// readData()
//// Read in the data from the last run. Contains file lengths, script lengths and other info.
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

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// writeData()
//// Save the data so it can be compared on the next run. File lengths, script lengths, etc.
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
	if($fileHandle = fopen($path.CYBERDIR.$cleanBase.STOREFILE,'w+')) {
		rewind($fileHandle);
		fwrite($fileHandle, serialize($store));
		fclose($fileHandle);
	}
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// writeLog()
//// Write an entry to the log file. This is called once at the end of run.
function writeLog($path, $base, $message) {
	if (isset($base) && (strlen($base)>0)) {
		$cleanBase = str_replace('/', '-', $base);
	}
	else {
		$cleanBase = '';
	}
	if($logHandle = fopen($path.CYBERDIR.$cleanBase.LOGFILE,'a')) {
		rewind($logHandle);
		fwrite($logHandle, $message);
		fclose($logHandle);
	}
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// echoAndLog()
//// Put a string on stdout as well as adding it to the (possible) log entry.
function echoAndLog($string) {
	global $logEntry;
	if (isset($string)) {
		echo $string."<br>\n";
		$logEntry .= $string."\r\n";
	}	
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// ifGetOrPost()
//// Looks for a parameter (in $_GET) or input (in $_POST) by name.
//// Returns 'null' if none found.
function ifGetOrPost($name) {
	if (isset($_GET[$name])) {
		return $_GET[$name];
	}
	if (isset($_POST[$name])) {
		return $_POST[$name];
	}
	return null;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// paramValue()
//// Gets the integer value of a GET or POST parameter or input.
function paramValue($paramName) {
	if (isset($_GET[$paramName]))  {
		try {
			if (($md = intval($_GET[$paramName])) > 0)
				return $md;
		}
		catch (Exception $pvx) {
		}
	}
	if (isset($_POST[$paramName]))  {
		try {
			if (($md = intval($_POST[$paramName])) > 0)
				return $md;
		}
		catch (Exception $pvx) {
		}
	}
	// No parameter, return "0" which is "failure"
	return 0;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// paramString()
//// Gets the string value of a GET or POST parameter or input.
function paramString($paramName) {
	if (isset($_GET[$paramName]))  {
		return $_GET[$paramName];
	}
	if (isset($_POST[$paramName]))  {
		return $_POST[$paramName];
	}
	// No parameter, return "" which is usually ignorable
	return "";
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// nValue()
//// Gets the value of the 'report=n' parameter. (Spidering depth)
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
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// stripos_array()
//// Searches an array of strings to see if a particular string is present.
//// Returns only 'true' or 'false'
function stripos_array($haystack, $needleArray) {
	foreach ($needleArray as $needle) {
		if (stripos($haystack, $needle) !== false) {
			return true;
		}
	}
	return false;
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
//// spiderThis()
//// Performs the main spidering function.
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
    global $myName;		// "just the filename" of this script
    
	// Be sure we're working with a directory
	if (is_dir($baseDirectory) && ($maxDepth>$depth)) {
		try {
			$depth++;
			$dirContents = dir($baseDirectory);
			// Run through this directory
			// WARNING: Under circumstances I have been unable to understand, sometimes the
			//   'read()' below just fails and the PHP script stops executing. No exception
			//   is caught here. The script just dies. It may be related to directories 
			//   containing a zero-length file, or perhaps some filesystem corruption. I have
			//   not really found the cause, nor a way to avoid it.  [SKY 2012-03-02]
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
							if(($len > 3) and !(strripos($thisEntry, "log") == ($len-3)) and !(strripos($thisEntry, SPIDERFILE) == ($len-strlen(SPIDERFILE))) and !(strripos($thisEntry, STOREFILE) == ($len-strlen(STOREFILE)))) {
								// Note: Ignore files ending in "log"
								// Note: Ignore files ending with the name of our data file
								// Otherwise, note a changed size
								echoAndLog("New size: ".$status[$thisEntry]." -> [$fileSize] $thisEntry ");
								$newSizes++;
							}
						}
					}
					
					// And scan PHP/HTML/HTM/JS files for eval and gzinflate and base64
					// Note: skips self ($myName)
					try {
						$len = strlen($thisEntry);
						if(($len > 4) and ((strripos($thisEntry, ".php") == ($len-4))
						|| (strripos($thisEntry, ".htm") == ($len-4))
						|| (strripos($thisEntry, ".html") == ($len-5))
						|| (strripos($thisEntry, ".js") == ($len-3))
						) and (stripos($thisEntry, '/'.$myName)===false)) {
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
// MAIN script - this is executed when the file is invoked by HTTP GET or HTTP PUT

// Get the filesystem path to this file (only the PATH) including an ending "/"
$path = substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
$myName = $_SERVER['SCRIPT_FILENAME'];
$myName = substr($myName, strrpos($myName, '/')+1);

// Set up to calculate CPU utilization
$cpuStat = ifGetOrPost('cpu');
if (!isset($cpuStat) || $cpuStat == null || $cpuStat == 0) {
	unset($cpuStat);
}
if (isset($cpuStat)) {
	$statFile = file_get_contents('/proc/stat');
	try{
		$statLines = explode("\n", $statFile);
		foreach ($statLines as $statLine) {
			$stat = explode(' ', $statLine);
			if (strcasecmp('cpu', $stat[0]) == 0) {
				while ($stat[1]=='') {
					array_splice($stat, 1, 1);
				}
				$cpu = $stat[1];
			}
		}
	}
	catch (Exception $slx) {
	}
	$cpuStart = time();		// this is seconds
}

// 'disk=none' or 'disk=filesystembase
if (($disk = ifGetOrPost('disk')) != null) {
	if (strcasecmp($disk, 'none') == 0) {
		$fsPath = null;				// causes silent fail
	}
	else {
		$fsPath = '/' . $disk;		// note ADD LEADING '/' to help find mount point because '/' can't be in the URL !
		$fsPath = str_replace('//', '/', $fsPath);
	}
}
// Disk/filesystem space used
$diskTotalSpace = 0;
$diskUsedSpace = 0;
$diskReport = '';
if ($fsPath != null) {
	$diskReport .= "Checking filesystem at $fsPath \r\n"; 
	try {
		if (function_exists('disk_total_space')) {
			$diskTotalSpace = disk_total_space($fsPath);
		}
		else {
			$diskReport .= " Function 'disk_total_space' does not exist. \r\n"; 
		}
		if (function_exists('disk_free_space')) {
			$diskUsedSpace = $diskTotalSpace - disk_free_space($fsPath);
		}
		else {
			$diskReport .= " Function 'disk_free_space' does not exist. \r\n"; 
		}
		if ($diskTotalSpace > 0) {
			// Note: $diskPercentUsed is only defined if total space > 0
			$diskPercentUsed = $diskUsedSpace/$diskTotalSpace * 100;
		}
		}
	catch (Exception $sda) {
		$diskReport .= " Exception: ".$sda->getMessage."\r\n"; 
	}
}

header('Content-Type: text/html; charset=UTF-8');

// '/help'  - - - - - - - - - - - -
if (isset($_GET['help']) || isset($_POST['help'])) {
	//  /help
	echo "<html>\n<body width=600px align=left>
	/help          <br>
	/path          <br>
	/report        <br>
	/report=n      <br>
	/report=n&base=xxxxxxx      <br>
	  <p style='margin-left:30px;width:570px;'>Produces a report on status of all PHP files.
	  If 'n' (a number) is present the site is spidered only to this maximum depth.
	  (Start by using 1 or 2 for the depth until you know how quickly your server can
	  perform this task.)  </p>
	  <p style='margin-left:30px;width:570px;'>Run several times to establish a baseline, then in the future
	  you can watch for any significant changes.
	  </p>
	  <p style='margin-left:30px;width:570px;'>If base=xxxxxxx is specified then the report starts at
	  directory /xxxxxxx with respect to where the CyberSpark PHP is located
	  </p>
	/ignore=aaa,bbb,ccc      <br>
	/exclude=aaa,bbb,ccc      <br>
	/except=aaa,bbb,ccc      <br>
	  <p style='margin-left:30px;width:570px;'>Excludes/ignores directories and files
	  containing any of the specified strings ('aaa' 'bbb' 'ccc' separated by commas).  </p>
	/disk=dev       <br>
	/disk=none      <br>
	  <p style='margin-left:30px;width:570px;'>Reports disk (filesystem) usage and capacity if a 'dev' is specified
	  or Skips disk (filesystem) capacity reporting if =none is specified.  
	  Common usages are disk=dev/sda or disk=home/username</p>
	</body>\n</html>\n";
	return;
}

// '/path' - - - - - - - - - - - - -
if (isset($_GET['path'])) {
	//  /path
	echo "<html>\r\n<body>\r\n<div align='left'> $path </div>\r\n</body>\r\n</html>\r\n";
	return;
}

// 'except=xxxxxxx' or 'ignore=xxxxxxx' or 'exclude=xxxxxxx'
// (Note that if you have more than one of them, this code will not work properly)
$xs = paramString('except') . paramString('ignore') . paramString('exclude');
if (strlen($xs) > 0) {
	$exclude = array_merge($exclude, explode(',', $xs));
}

// 'wordpress'
if (isset($_GET['wordpress']) || isset($_POST['wordpress'])) {
	$exclude = array_merge($exclude, $wordpress);
}

// '/report'  detection  - - - - - - -
if (isset($_GET['report']) or isset($_POST['report'])) {
	// Set flag for actions to perform
	$report = true;
}

// '=n'  detection+processing  - - - -
	$maxDepth = nValue();

// 'base=xxxxxxx' detection+processing 
if ($report) {
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
if ($report) {
	//  /report
	$fullPath = $path . $base;
	echo "<html>
	<body width='600px'> 
	<p style='margin-left:30px;width:570px;'>
	CyberSpark local agent report<br>
	Base directory is $fullPath <br>
	My name is $myName <br>
	Spidering depth will be $maxDepth <br>
	Any changes reported below are 'since the last time this script was run.'<br>
	Do not stop this script or leave this page until it finishes.
	</p>
";
	// The html is closed off later on after the scan has been completed
}

// read previous file status info  - - - -
readData($path, $base);

$logEntry .= "\r\n///////////////////////////////////\r\n".date('r')."\r\n";
$logEntry .= "Base $fullPath\n\rDepth $maxDepth\r\n";

// the bulk of processing takes place HERE   - - - - -
if ($report) {
	spiderThis($path.$base, $maxDepth);	// spider with a maximum depth Example: "1" limits to top directory
	echo "<br>\r\n";
}

// reporting out - - - - - - - - - - - - -
if ($report) {
	echo "\r\n<br><br>- - - - - - - - - - - - - - - - -<br>\n";
	echoAndLog ("Summary report");
	echoAndLog ("Total files: $totalFiles");
	if ($phpFiles > 0) {
		echo "<br>\r\n";
		echoAndLog( "PHP files examined: $phpFiles");
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

// Calculate CPU utilization
if (isset($cpuStat)) {
	sleep($cpuStat);		// ensure sleep at least as long as requested
	$statFile = file_get_contents('/proc/stat');
	try{
		$statLines = explode("\n", $statFile);
		foreach ($statLines as $statLine) {
			$stat = explode(' ', $statLine);
			if (strcasecmp('cpu', $stat[0]) == 0) {
				while ($stat[1]=='') {
					array_splice($stat, 1, 1);
				}
				$cpuSeconds = (time() - $cpuStart);
				$cpu = ($stat[1] - $cpu)/$cpuSeconds;
			}
		}
	}
	catch (Exception $slx) {
	}
}

//  /report
if ($report) {
	echoAndLog ("New files:     $newFiles");
	echoAndLog ("Changed sizes: $newSizes");
	echoAndLog ("Files gone:    ".sizeof($status));
	
	// Report on system load
	$loads = sys_getloadavg();
	echoAndLog ("Load:          ".$loads[0]." ".$loads[1]." ".$loads[2]);
	if (isset($cpuStat)) {
		echoAndLog ("CPU:           ".(int)$cpu.'%');
	}
	// Report on how full the disk/filesystem is
	if (isset($fsPath) && (strlen($fsPath) > 0)) {
		if (strlen($diskReport) > 0) {
			echoAndLog ($diskReport);
		}
		if (isset($diskPercentUsed)) {
			echoAndLog (sprintf('Disk: %000d%% used of %000d GB total on "%s"', $diskPercentUsed, $diskTotalSpace/1000000000, $fsPath));
		}
	}
}

// closing the HTML - - - - - - - - - - - -
$logEntry .= "\r\n";

if ($report) {
	writeData($path, $base);
	writeLog ($path, $base, $logEntry);
	echo "</div>\r\n</body>\r\n</html>\r\n";
}

?>
