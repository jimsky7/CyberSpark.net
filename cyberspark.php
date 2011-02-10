#!/usr/bin/php -q
<?php
	/**
		CyberSpark.net monitoring-alerting system
		called from the command line as
		  /usr/local/cyberspark/cyberspark.php --arg --arg
	*/

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";

declare(ticks = 1);					// allows shutdown functions to work
include_once "include/shutdown.inc";
include_once "include/startup.inc";
include_once "include/functions.inc";

///////////////////////////////// 
// 
$ID			= INSTANCE_ID;			// "short" ID (like "CS1")
$identity	= DEFAULT_IDENTITY;		// from config
$userAgent  = DEFAULT_USERAGENT;	// from config
$maxDataSize= MAX_DATA_SIZE;		// maximum serialized data file size = 10MB
$dataDir	= DATA_DIR;				// where data will live
$propsDir	= PROPS_DIR;			// where properties files live
$filtersDir	= FILTERS_DIR;			// where the scanning filters live
$logDir		= LOG_DIR;				// where the csv logs will be written
$propsFileName= "";					// exact name of the properties file
$storeFileName=	DATA_EXT;			// filename for the data store
$logFileName  = LOG_EXT;			// filename for log
$logHandle	= null;					// handle for log file (used as a global elsewhere)
$path		= APP_PATH;				// path to the executing script (WITHOUT script file name)
$scriptName	= "";					// this script's name, picked up from $argv[0]
$isDaemon   = false;				// true if running in 'daemon' mode
$configTest = false;            	// run in TEST mode- just read properties
$running	= false;
$time		= DEFAULT_LOOP_TIME;	// default minutes between loops
$notify		= DEFAULT_NOTIFY_HOUR;	// default "midnight hour" is "23	"
$messageSeed= "";					// initial portion of message
$host		= "";					// human-readable host name
$loop		= 0;
$crashes	= 0;
$properties = array();				// properties as returned by getProperties()
$timeout    = DEFAULT_SOCKET_TIMEOUT;	// socket timeout in seconds for HTTP GET (from config)
	
// Data store for "private" information that filters want to retain between runs.
// The main CyberSpark system can also persist data here.
//   ...as    $store['cyberspark']['key'] = value;
$store		= null;					// persistent storage for filters

// Google Safe Browsing interface parameters
$gsbServer = GSB_SERVER;			// from config

///////////////////////////////// 
// Filter-related stuff
// Filters are *.inc files within the filters/ subdirectory that contain code to
//   be applied to the URLs we examine.  See filters/basic.php for internal
//   documentation about how to write and "call" filters.
include "include/classdefs.inc";
$filters = array();					// this array is numerically ordered/indexed
									// and contains all filter information.
	
///////////////////////////////// 
// Email-related 
// All SMTP parameters are initially from the config, although the properties
//   file can supplant them.
// You can use gmail or you can use any other server you wish to define
//   that uses regular SMTP or secure (SSL) SMTP on any port you choose.
$smtpServer = SMTP_SERVER;					// default from config (properties can override)
$user		= SMTP_USER;					// default from config (properties can override)
$pass		= SMTP_PASSWORD;				// default from config (properties can override)
$from		= EMAIL_FROM;					// default from config (properties can override)
$smtpPort	= SMTP_PORT;					// default from config (properties can override)
$replyTo	= EMAIL_REPLYTO;				// default from config
$to			= EMAIL_TO;						// default from config (properties can override)
$abuseTo	= EMAIL_ABUSETO;				// default from config
	
///////////////////////////////// 
// include supporting code
include_once "include/store.inc";
include_once "include/args.inc";
include_once "include/properties.inc";
include_once "include/mail.inc";
include_once "include/http.inc";
include_once "include/scan.inc";
include_once "include/filters.inc";
include_once "include/echolog.inc";
	
///////////////////////////////// 
// initialization
// Get the filesystem path to this file (only the PATH) including an ending "/"
// NOTE: This overrides the APP_PATH from the config file, which will be unused.
$path 		= substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
$dataDir 	= $path . $dataDir;
$propsDir	= $path . $propsDir;
$filtersDir = $path . $filtersDir;
$logDir 	= $path . $logDir;
$scriptName	= $argv[0];

///////////////////////////////// 
// Parse the command-line arguments
getArgs($argv);
$propsFileName		= $propsDir . $ID . PROPS_EXT;
$storeFileName		= $dataDir  . $ID . DATA_EXT;
$logFileName		= $logDir   . $ID . LOG_EXT;
$pidFileName		= $path . $ID . PID_EXT;
$heartbeatFileName	= $path . $ID . HEARTBEAT_EXT;
$running = true;

// Register shutdown functions
try {
	pcntl_signal(SIGTERM, 'shutdownFunction');		// kill
	pcntl_signal(SIGINT,  'shutdownFunction');		// Ctrl-C
}
catch (Exception $x) {
	echo "Critical: $ID unable to register shutdown functions.\n";
}
	
// Write process ID to a file
// Note that if we were run from cybersparkd.php then this pid file is critical because
//   our parent is an 'sh' and not cybersparkd itself.  So the pid file is the only way
//   cybersparkd can find and terminate this script.
try {
	@unlink($pidFileName);
}
catch (Exception $x) {
	echo "Warning: $ID unable to delete process ID file.\n";
}
try {
	@file_put_contents($pidFileName, (string)posix_getpid());	// save process ID
//	echo("Wrote pid file $pidFileName \n");
}
catch (Exception $x) {
	echo "Critical: $ID unable to write process ID to 'pid' file.\n";
}
	
// Open the log file
beginLog();

// Send a "launched" message if running as a daemon.
// If running from command line one-time-only then send no message.
// Launched from command line it is entirely possible to run with no
//   messages to console and nothing else except alerts.
if ($isDaemon) {
	$subject = $ID . " Daemon launched " . date("r");
	$message = date("r") . "\nLaunched as a daemon\n";
	textMail($to, $from, $replyTo, $abuseTo, $subject, $message, $smtpServer, $smtpPort, $user, $pass);
}

///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// Loop starts here

	while ($running) {
		
		try {
			// (Always use protection)
			
			$loopStartTime = time();		// seconds (Unix time)

			// Check for existence of properties file.
			// If someone deletes it while this daemon is running,
			//   then we want to just quit gracefully.
			if (!file_exists($propsFileName)) {
				// Delete the PID file so cybersparkd won't try to relaunch this process
				@unlink($pidFileName);
				writeLogAlert("$ID The properties file has disappeared. This daemon will now end. $pidFileName");
				echoIfVerbose("$ID The properties file has disappeared. This daemon will now end. $pidFileName\n");
				break;
			}
			
			// Read the 'properties' file (the configuration) EVERY TIME AROUND
			$properties = getProperties($propsFileName);
			if (isset($properties['error']) || $configTest) {
				// Properties file failed in some way
				writeLogAlert("Failed to parse $propsFileName Error was: " . $properties['error']);
				echoIfVerbose("Failed to parse $propsFileName Error was: " . $properties['error']);
				break;
			}
			
			// Set some properties that can't be set in the properties files
			$properties['abuseto']	= EMAIL_ABUSETO;			// default from config
			$properties['replyto']	= EMAIL_REPLYTO;			// default from config
			$properties['shortid']	= $ID;
			$properties['gsbserver']= GSB_SERVER;				// default from config
			$properties['loop']		= $loop;
			$properties['crashes']	= $crashes;
			
			$properties = fixProperties($properties);
			
			if (isset($properties['smtpport'])) {
				$smtpPort	= $properties['smtpport'];
			}
			if (isset($properties['smtpserver'])) {
				$smtpServer	= $properties['smtpserver'];
			}
			if (isset($properties['host'])) {
				$host		= $properties['host'];
			}
			if (isset($properties['to'])) {
				$to			= $properties['to'];
			}
			if (isset($properties['pager'])) {
				$pager		= $properties['pager'];
			}
			if (isset($properties['from'])) {
				$from		= $properties['from'];
			}
			if (isset($properties['subject'])) {
				$subject	= $properties['subject'];
			}
			$messageSeed= $properties['message'];
			if (isset($properties['time'])) {
				$time		= $properties['time'];
			}
			if (isset($properties['notify'])) {
				$notify		= $properties['notify'];
			}
			if (isset($properties['user'])) {
				$user		= $properties['user'];
			}
			if (isset($properties['password'])) {
				$pass		= $properties['password'];
			}
			if (isset($properties['useragent'])) {
				$userAgent	= $properties['useragent'];
			}
			if (isset($properties['timeout'])) {
				$timeout	= $properties['timeout'];
			}
			$message = $messageSeed;
		
			echoIfVerbose("Verbose\n");
	
			// Write a 'heartbeat' value.  It is the time this process should next wake up.  
			//   A supervisory process that desires to monitor this process's performance can
			//   examine the heartbeat value in the file and if it has passed (within reason), then
			//   the supervising process could assume this process has failed, or hung somehow,
			//   and is no longer executing its primary loop.  The supervisory process could
			//   then decide to report back to a human, or could kill and restart this process.
			//   Note that we write this value at the -start of the loop- or "as early as
			//   possible after we wake up" so the supervisory process won't be tricked just
			//   because we get stuck on some particular HTTP-GET for a while.
			//   Note that although we could let the two processes communicate more directly,
			//   say through a socket, the file is a "silent" channel that cannot easily be
			//   intercepted or interrupted by an outside attacker.
			//   Keep in mind that sometimes the number of URLs to be examined may be high enough
			//   that this process can't complete everything during the requested loop time and
			//   might look dead even though it actually isn't, so the supervisory process should
			//   exercise care before killing this process (like maybe use -INT as a first try?), 
			//   or give the process significant slack (maybe an hour?) before terminating it.
			if ($isDaemon) {
				try {
					$heartbeatTime  = $loopStartTime;	// loop-started time in seconds (Unix time)
					$heartbeatTime += $properties['time'] * 60;	// change minutes to seconds
					@file_put_contents($heartbeatFileName, "$heartbeatTime");	// save next run time
					writeLogAlert("$ID Wrote heartbeat file. Predicted wakeup time= $heartbeatTime");
					echoIfVerbose("$ID Wrote heartbeat file. Predicted wakeup time= $heartbeatTime\n");
				}
				catch (Exception $x) {
					echo "Critical: $ID unable to write 'heartbeat' file. $heartbeatFileName\n";
				}
			}
						
			// Bring in the "store" of values saved for the filters
			if ($loop == 0) {
				if ($isDaemon) {
					writeLogAlert("Running as daemon");
				}
				else {
					writeLogAlert("Running one-time (not a daemon)");
				}
				$store = readStore($storeFileName, $maxDataSize);
				if (isset($store['cyberspark']['id'])) {
					echoIfVerbose("Using an existing store " . $store['cyberspark']['id'] . "\n");
				}
				else{
					echoIfVerbose("No store exists yet\n");
				}
				if ($store['cyberspark']['tripwire']==true) {
					writeLogAlert("Ouch that hurt! Apparently I wasn't shut down correctly - may have crashed.");
					echoIfVerbose("$ID Ouch that hurt! Apparently I wasn't shut down correctly - may have crashed.\n");
				}
				$store['cyberspark']['tripwire'] = true;
				$properties['notifiedtoday'] = $store['cyberspark']['notifiedtoday'];
			}
	 		// Add filters (only if there are none yet - first time around)
			if ($loop == 0) {
				findAndAddFilters($filtersDir, $properties);
				print_rIfVerbose($filters);
				initFilters($filters, $properties, $store);
			}
	
			$store['cyberspark']['id'] = $ID;
			$store['cyberspark']['loop'] = $loop;
	
			///////////////////////////////////////////////////////////////////////////////////
			///////////////////////////////////////////////////////////////////////////////////
			
			$scanResults = scan($properties, $filters, $store);
			
			///////////////////////////////////////////////////////////////////////////////////
			///////////////////////////////////////////////////////////////////////////////////
			
			sendMail($scanResults, $properties);
			
			///////////////////////////////////////////////////////////////////////////////////
			///////////////////////////////////////////////////////////////////////////////////
	
			// Save the "store" of private values from the filters
			// This happens every time around the loop, though we don't read
			//   them back each time.  Saving each time around ensures that even in
			//   the event of a crash we will have recent values.
			writeStore($storeFileName, $store);
			echoIfVerbose("Wrote contents of 'store' to $storeFileName \n");
			
			///////////////////////////////////////////////////////////////////////////////////
			///////////////////////////////////////////////////////////////////////////////////
			// If not running 'daemon' then drop out now
			if ($isDaemon) {
				$loop++;
				// Calculate sleep time based on param from properties
				$loopElapsedTime = time() - $loopStartTime;		// elapsed time in seconds
				$desiredLoopTime = $properties['time'] * 60;	// change minutes to seconds
				$sleepTime = $desiredLoopTime - $loopElapsedTime;
				if ($sleepTime < 0) {
					$sleepTime = 1;
				}
				echoIfVerbose("Going to sleep for $sleepTime seconds. (The loop took $loopElapsedTime seconds.)\n");
				sleep($sleepTime);		// sleep (seconds)
			}
			else {
				break;
			}
		
		}
		catch (Exception $x) {
			writeLogAlert("cyberspark.php main loop exception: " . $x->getMessage());
			echoIfVerbose("cyberspark.php main loop exception: " . $x->getMessage());
		}
	
		// End of daemon loop (non-deamon already executed 'break' above and dropped out)
	}

	///////////////////////////////// 
	// clean up
	doBeforeExit();	

	exit;


///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 


?>