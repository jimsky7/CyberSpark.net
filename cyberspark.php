#!/usr/bin/php -q
<?php
/****
		CyberSpark.net monitoring-alerting system
		
		cyberspark.php represents one "agent" and many of these may be running on the
		same server under the control of the cybersparkd.php daemon.

		Test from the command line by shutting down cybersparkd (daemon) and then
		running an individual agent using the commands below. Be sure to wait after
		turning off the cyberspark service until all agents stop running.
			service cyberspark stop
			cd /usr/local/cyberspark
			php ./cyberspark.php --id CS0-0
		(or whatever CS you want to run as - it picks up the matching properties)
****/

///////////////////////////////////////////////////////////////////////////////////
// Local (your installation) variables, definitions, declarations
include_once 'cyberspark.config.php';
// CyberSpark system variables, definitions, declarations
include_once 'cyberspark.sysdefs.php';

declare(ticks = 1);					// allows shutdown functions to work
include_once 'include/shutdown.php';
include_once 'include/startup.php';
include_once 'include/functions.php';

///////////////////////////////////////////////////////////////////////////////////
// 
$ID			= INSTANCE_ID;			// this "agent" ID (example "CS9-0")
$identity	= DEFAULT_IDENTITY;		// from config
$userAgent  = DEFAULT_USERAGENT;	// from config
$maxDataSize= MAX_DATA_SIZE;		// maximum serialized data file size = 10MB
$dataDir	= DATA_DIR;				// where data will live
$propsDir	= PROPS_DIR;			// where properties files live
$filtersDir	= FILTERS_DIR;			// where the scanning filters live
$logDir		= LOG_DIR;				// where the csv logs will be written
$propsFileName= '';					// exact name of the properties file
$storeFileName=	DATA_EXT;			// filename for the data store
$logFileName  = LOG_EXT;			// filename for log
$logHandle	= null;					// handle for log file (used as a global elsewhere)
$path		= APP_PATH;				// path to the executing script (WITHOUT script file name)
$scriptName	= '';					// this script's name, picked up from $argv[0]
$isDaemon   = false;				// true if running in 'daemon' mode
$running	= false;
$time		= DEFAULT_LOOP_TIME;	// default minutes between loops
$notify		= DEFAULT_NOTIFY_HOUR;	// default "midnight hour" is "23	"
$messageSeed= '';					// initial portion of message
$host		= '';					// human-readable host name
$loop		= 0;
$crashes	= 0;
$properties = array();					// properties as returned by getProperties()
$timeout    = DEFAULT_SOCKET_TIMEOUT;	// socket timeout in seconds for HTTP GET (from config)
	
// Data store for "private" information that filters want to retain between runs.
// The main CyberSpark system can also persist data here.
//   ...as    $store['cyberspark']['key'] = value;
$store		= null;					// persistent storage for filters

// Google Safe Browsing interface parameters
$gsbServer = GSB_SERVER;			// from config

///////////////////////////////////////////////////////////////////////////////////
// Filter-related stuff
// Filters are *.php files within the filters/ subdirectory that contain code to
//   be applied to the URLs we examine.  See filters/basic.php for internal
//   documentation about how to write and "call" filters.
include 'include/classdefs.php';
$filters = array();					// this array is numerically ordered/indexed
									// and contains all filter information.
	
///////////////////////////////////////////////////////////////////////////////////
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
	
///////////////////////////////////////////////////////////////////////////////////
// include supporting code
include_once 'include/store.php';
include_once 'include/args.php';
include_once 'include/properties.php';
include_once 'include/mail.php';
include_once 'include/http.php';
include_once 'include/scan.php';
include_once 'include/filters.php';
include_once 'include/echolog.php';
	
///////////////////////////////////////////////////////////////////////////////////
// initialization
// Get the filesystem path to this file (only the PATH) including an ending "/"
// NOTE: This overrides the APP_PATH from the config file, which will be unused.
$path 		 = substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
$dataDir 	 = $path . $dataDir;
$propsDir	 = $path . $propsDir;
$filtersDir  = $path . $filtersDir;
$logDir 	 = $path . $logDir;
$scriptName	 = $argv[0];

///////////////////////////////////////////////////////////////////////////////////
// Parse the command-line arguments
list($isDaemon, $ID) = getArgs($argv);
$propsFileName		 = $propsDir . $ID . PROPS_EXT;
$storeFileName		 = $dataDir  . $ID . DATA_EXT;
$logFileName		 = $logDir   . $ID . LOG_EXT;
$pidFileName		 = $path . $ID . PID_EXT;
$heartbeatFileName	 = $path . $ID . HEARTBEAT_EXT;
$urlFileName         = $path . $ID . URL_EXT;
$rotFileName		 = $path . $ID . ROT_EXT;
$running = true;
$pipes = null;
$logTransportProcess = null;

///////////////////////////////////////////////////////////////////////////////////
// Define the shutdown function
// Note that if this process becomes 'unresponsive' the shutdown will not be
//   performed. Specifically, this could leave a log-transport running.
function shutdownFunctionWrapper($sig) {
	return shutdownFunction($sig);		// in includes/shutdown.php
}
///////////////////////////////////////////////////////////////////////////////////
// Register the shutdown function
try {
	pcntl_signal(SIGTERM, 'shutdownFunctionWrapper');		// kill
	pcntl_signal(SIGINT,  'shutdownFunctionWrapper');		// Ctrl-C
}
catch (Exception $x) {
	echo "Critical: $ID unable to register shutdown functions.\n";
}
	
///////////////////////////////////////////////////////////////////////////////////
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
	
///////////////////////////////////////////////////////////////////////////////////
// Open the log file
beginLog();

// Send a "launched" message if running as a daemon.
// If running from command line one-time-only then send no message.
// Launched from command line it is entirely possible to run with no
//   messages to console and nothing else except alerts.
if ($isDaemon) {
	$subject = "$ID Daemon launched " . date('r');
	$message =  "$ID was launched as a daemon.\n";
	$message .=  date('r')."\n";
	$message .= "The default hour for notifications and log rotations will be $notify:00 UTC (24-hour clock)\n";
	$message .= "The properties file may change this to a different time.\n";
	textMail($to, $from, $replyTo, $abuseTo, $subject, $message, $smtpServer, $smtpPort, $user, $pass);
}

///////////////////////////////////////////////////////////////////////////////////
// Launch a log-transport process.
if (file_exists('log-transport.php')) {
	$timeStamp = date("r");
	echo "$ID is launching its log transport - $timeStamp\n";	
	$descriptorspec = array(
  		0 => array('pipe', 'r'),  // stdin is a pipe that the child will read from
  		1 => array('pipe', 'w'),  // stdout is a pipe that the child will write to
  		2 => array('file', "/tmp/stderr-log-transport-$ID.txt", "a") // stderr is a file to write to
	);	
	// Launch a child process corresponding to self.
	// Actually a 'sh' gets launched, and that in turn runs 'php' which picks up the
	//   cyberspark.php script that does the actual monitoring.  So there are two levels
	//   of child proces below us now.  First is the 'sh' and below that a 'php' instance.
	//   The 'php' instance of cyberspark.php records its process id in a 'pid' file.
	// Later on we will first use the process ID from the 'pid' file to kill the 'php'
	//   process.  Then when that terminates, we read any stdout it has produced, and we
	//   terminate the 'sh' as well.
	$logTransportProcess = proc_open("php $path".LOG_TRANSPORT." --id $ID", $descriptorspec, $pipes);
}

///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// Loop starts here

	while ($running) {
		
		try {
			// (Always use protection)
			
			$loopStartTime = time();		// seconds (Unix time)

			echoIfVerbose("\n/////////////////////////////////////////////////////\nLoop begins " . $loop . "\n");

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
			// (Specify {ID} as a substitutable string)
			// (Specify {uname} to get Ubuntu info)
			$uname = '';
			if (is_file('/etc/issue.net')) {
				$uname = file_get_contents('/etc/issue.net');
			}
			$instanceLocation = defined('INSTANCE_LOCATION')?INSTANCE_LOCATION:'';
			$properties = getProperties($propsFileName, array('ID' => $ID, 'version' => CYBERSPARK_VERSION, 'uname' => $uname, 'location' => $instanceLocation));
			if (isset($properties['error'])) {
				// Properties file failed in some way
				writeLogAlert("Failed to parse $propsFileName Error was: " . $properties['error']);
				echoIfVerbose("Failed to parse $propsFileName Error was: " . $properties['error']);
				break;
			}
			
			// Add some properties if they're not in the properties files
			if (!isset($properties['abuseto'])) {
				$properties['abuseto']	= EMAIL_ABUSETO;			// default from config
			}
			if (!isset($properties['replyto'])) {
				$properties['replyto']	= EMAIL_REPLYTO;			// default from config
			}
			
			// Set some properties that can't ever be set in the properties files
			$properties['shortid']	= $ID;
			$properties['gsbserver']= GSB_SERVER;				// default from config
			$properties['loop']		= $loop;
			$properties['crashes']	= $crashes;
			
			$properties = fixProperties($properties);
			
			// Set some local variables
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
			if (isset($properties['time'])) {
				$time		= $properties['time'];
			}
			if (isset($properties['notify'])) {
				// If the properties file contained a 'notify' specification
				// then override the default from the config file
				$notify		= $properties['notify'];
			}
			else {
				// Otherwise, propagate the default value from the config file.
				// Note that $properties[] is sent to various places which might
				//   need to know whether it's the "notify" hour or not.
				$properties['notify'] = $notify;
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
			
			// Place to write URL for debugging purposes
			$properties['urlfilename'] = $urlFileName;
			
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
					echoIfVerbose("Using an existing datastore " . $store['cyberspark']['id'] . "\n");
				}
				else{
					echoIfVerbose("No datastore exists yet\n");
					writeLogAlert("Created a new datastore");
				}
				if ($store['cyberspark']['tripwire']==true) {
					writeLogAlert("Ouch that hurt! Apparently this process wasn't shut down correctly - may have crashed.");
					echoIfVerbose("$ID Ouch that hurt! Apparently this process wasn't shut down correctly - may have crashed.\n");
				}
				$store['cyberspark']['tripwire'] = true;
				if (isset($store['cyberspark']['notifiedtoday'])) {
					if (!isNotifyHour($notify)) {
						// If it's not the 'notify' hour, force the flag false.
						// This fix can be needed if the daemon was running,
						//   sent notification, then was shut down during DURING the notify hour, 
						//   but then was restarted during a LATER hour. Obscure, but it 
						//   will latch the daemon into perpetually rotating the log every
						//   time around the loop.
						$store['cyberspark']['notifiedtoday'] = false;
					}
					$properties['notifiedtoday'] = $store['cyberspark']['notifiedtoday'];
					$prenotified                 = $store['cyberspark']['notifiedtoday'];
				}
				else {
					$properties['notifiedtoday'] = false;
					$prenotified                 = false;
				}
			}
			else {
				// Reset values of things that are in the store but not the properties file
				$properties['notifiedtoday'] = $store['cyberspark']['notifiedtoday'];
				$prenotified                 = $store['cyberspark']['notifiedtoday'];
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
			$store['cyberspark']['notifiedtoday'] = $properties['notifiedtoday'];
			$postnotified                         = $properties['notifiedtoday'];

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
			// Rotate logs and if asked send properties and/or logs out to administrator
			$dateTimeNumbers = date('YmdHis');
// debugging below
//				$sa = $prenotified?'true':'false';
//				$sb = $postnotified?'true':'false';
//				$sc = $isDaemon?'true':'false';
//				$sd = isset($properties['sendlogs'])?$properties['sendlogs']:'undefined';
//				echoIfVerbose("Prenotified: $sa Postnotified: $sb Daemon: $sc Sendlogs: $sd \n");
// echo ("Prenotified: $sa Postnotified: $sb Daemon: $sc Sendlogs: $sd \n");
// debugging above
			if ((!$prenotified && $postnotified) || !$isDaemon)  {
				// Note that we only do this during the daily notification hour
				// The 'rotate' keyword causes us to rotate files once a day.
				// It can be bare, or with a logical value
				//   rotate
				// or
				//   rotate=true
				//   rotate=false
				$rotate = isset($properties['rotate']);		// is true if 'rotate' is even present
				if ($rotate) {
					// If the keyword is present at all, it is 'true' at this point.
					// If it contains a "=true" or "=false" value, then we check it
					if (is_string($properties['rotate']) && (strlen($properties['rotate']) > 0) && ($properties['rotate'] != strtolower('true'))) {
						$rotate = false;
					}
				}
				// Sendlogs
				// keyword and value tell where to send rotated logs
				//   sendlogs=email@example.com
				$sendLogs = isset($properties['sendlogs']) && ($properties['sendlogs'] != null) && (strlen($properties['sendlogs']) > 0);
				$sendProps = isset($properties['sendproperties']) && ($properties['sendproperties'] != null) && (strlen($properties['sendproperties']) > 0);
				// Do various things connected with log rotation and/or sending to administrator
				// Note that we can rotate without sending, but we cannot send without rotating.
				$rotate = $rotate || $sendLogs || $sendProps;		// rotate if specifically asked for OR if sending files
				// So if we are rotating, then there are things to do...
				if ($rotate) {
					///////////////////////////////////////////////////////////////////////////////////
					///////////////////////////////////////////////////////////////////////////////////
					// Send (a possibly updated) properties file to our admin once a day
					// It's time to send
					try {
						$saveTo = $properties['to'];
						if ($sendProps) {
							$properties['to'] = $properties['sendproperties'];
							echoIfVerbose("Sending a copy of the properties file to $properties[sendproperties]\n");
							writeLogAlert("Sending a copy of the properties file to $properties[sendproperties]");
							if (defined(PROPS_UNIQUE_COPY) && PROPS_UNIQUE_COPY) {
								// Use this code to send a copy with a unique filaname based on the current time
								$copyFileName = str_replace(PROPS_EXT, '-'.$dateTimeNumbers.PROPS_EXT, $propsFileName);
								if (copy($propsFileName,$copyFileName)) {
									sendMailAttachment('Props', 'A copy of the properties file for '.$ID.' is attached.', $properties, $copyFileName);
									unlink($copyFileName);
								}
							}
							else {
								// Use this code to send using the original props filename -- will be the same every time
								sendMailAttachment('Props', 'A copy of the properties file for '.$ID.' is attached.', $properties, $propsFileName);
							}
						}
						///////////////////////////////////////////////////////////////////////////////////
						///////////////////////////////////////////////////////////////////////////////////
						// Send (and rotate) the log file to our admin once a day
						// It's time to send
						$copyFileName = str_replace(LOG_EXT, '-'.$dateTimeNumbers.LOG_EXT, $logFileName);
						// Close the log file so it can be zipped.
						echoIfVerbose("Rotating the log file. \n");
						writeLogAlert('Rotating the log file.');
						endLog();
						// Rename the log file (added timestamp)
						rename($logFileName, $copyFileName);
						echoIfVerbose("Renamed $logFileName $copyFileName \n");
						// gzip the log file. gzip will replace the old file with
						//   a zipped version ending in ".gz"
						// Use shell_exec() to launch and forget. The zip should run independently.
						// Any output error messages are ignored.
						shell_exec ("gzip $copyFileName &> /dev/null &");
						$copyFileName .= ZIP_EXT;					// new name after gz
						echoIfVerbose("Zipped to $copyFileName\n");
						// Start a new log file
						beginLog();
						if ($sendLogs) {
							$properties['to'] = $properties['sendlogs'];
							echoIfVerbose("Sending the log to $properties[sendlogs] \n");
							writeLogAlert("Sending the log to $properties[sendlogs].");
							// Email the zipped log to administrator
							// See if it should be sent to a special email address
							//   logs=email@example.com
							// ...in the properties file
							sendMimeMail('Log attached', "A gzipped copy of the log file for $ID is attached.", $properties, $copyFileName);
						}
						else {
							// No longer send an email when log file is rotated 2018-04-02
							//	sendMimeMail('Log rotation', "The log file for $ID has been rotated. A gzipped copy remains on the server.", $properties, null);
						}
						// Set a flag (file) indicating logs have been rotated. This is used by parent process.
						@file_put_contents($rotFileName, "$ID; Log rotated ".date("r"));	// this file is a flag saying "logs rotated"
					}
					catch (Exception $zx) {
						echoIfVerbose('Exception when rotating logs or sending files: '.$zx->getMessage()." \n");
					}
					$properties['to'] = $saveTo;
				}
			}

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
					$took = floor($loopElapsedTime/60);
					$want = floor($desiredLoopTime/60);
					writeLogAlert("The loop ran longer than the desired time. You may wish to divide the URLs among two or more properties files. (The loop took $took minutes. The desired time was $want minutes.)");
					echoIfVerbose("The loop ran longer than the desired time. You may wish to divide the URLs among two or more properties files. (The loop took $took minutes. The desired time was $want minutes.)\n");
					sendMimeMail('Overrun', "The loop ran longer than the desired time. You may wish to divide the URLs among two or more properties files. (The loop took $took minutes. The desired time was $want minutes.)", $properties, null);
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
	shutdownFunctionWrapper(NORMAL_EXIT);
//	doBeforeExit();	

	exit;


///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 


?>