#!/usr/bin/php -q
<?php
	/**
		CyberSpark.net monitoring-alerting system
		called from the command line as
		  /usr/local/cyberspark/cyberspark.php --arg --arg
	*/

// Local (your installation) variables, definitions, declarations
include_once "cyberspark.config.php";
// CyberSpark system variables, definitions, declarations
include_once "cyberspark.sysdefs.php";

declare(ticks = 1);					// allows shutdown functions to work
include_once "include/shutdown.inc";
include_once "include/startup.inc";
include_once "include/functions.inc";

///////////////////////////////// 
// 
$ID			= INSTANCE_ID;			// this "sniffer" ID (like "CS9-0")
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
$urlFileName        = $path . $ID . URL_EXT;
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
			$properties = getProperties($propsFileName);
			if (isset($properties['error'])) {
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
			// Seed value for messages
			$message = '';
			if (isset($properties['message'])) {
				$message = $properties['message'];
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
				}
				if ($store['cyberspark']['tripwire']==true) {
					writeLogAlert("Ouch that hurt! Apparently this process wasn't shut down correctly - may have crashed.");
					echoIfVerbose("$ID Ouch that hurt! Apparently this process wasn't shut down correctly - may have crashed.\n");
				}
				$store['cyberspark']['tripwire'] = true;
				if (isset($store['cyberspark']['notifiedtoday'])) {
					if (!isNotifyHour($properties['notify'])) {
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
				// Do various things connected with log rotation and/or sending to administrator
				// Note that we can rotate without sending, but we cannot send without rotating.
				$rotate = $rotate || $sendLogs;		// rotate if specifically asked for OR if sending logs
				// So if we are rotating, then there are things to do...
				if ($rotate) {
					///////////////////////////////////////////////////////////////////////////////////
					///////////////////////////////////////////////////////////////////////////////////
					// Send (a possibly updated) properties file to our admin once a day
					// It's time to send
					try {
						$saveTo = $properties['to'];
						if ($sendLogs) {
							echoIfVerbose("Props and logs will be sent to $properties[sendlogs] \n");
							$properties['to'] = $properties['sendlogs'];
							echoIfVerbose("Send a copy of the properties file \n");
							$copyFileName = str_replace(PROPS_EXT, '-'.$dateTimeNumbers.PROPS_EXT, $propsFileName);
							if (copy($propsFileName,$copyFileName)) {
								sendMailAttachment('Props', 'A copy of the properties file for '.$ID.' is attached.', $properties, $copyFileName);
								unlink($copyFileName);
							}
						}
						///////////////////////////////////////////////////////////////////////////////////
						///////////////////////////////////////////////////////////////////////////////////
						// Send (and rotate) the log file to our admin once a day
						// It's time to send
						$copyFileName = str_replace(LOG_EXT, '-'.$dateTimeNumbers.LOG_EXT, $logFileName);
						$copyFileName .= ZIP_EXT;
						echoIfVerbose("Rotating the log file. \n");
						// Close the log file so it can be zipped.
						writeLogAlert('Closing the log in order to rotate.');
						endLog();
						// gzip the log file
						$z = gzopen($copyFileName, 'w9');
						$log = fopen($logFileName,'rb');
						while (!feof($log)) {
							gzwrite($z, fread($log, 100000));	
						}
						fclose($log);
						gzclose($z);
						// unlink the file that was just zipped
						// zipped logs are not removed
						unlink($logFileName);
						// Start a new log file
						beginLog();
						if ($sendLogs) {
							echoIfVerbose("Sending the log. \n");
							writeLogAlert('Sending the log.');
							// Email the zipped log to administrator
							// See if it should be sent to a special email address
							//   logs=email@example.com
							// ...in the properties file
							sendMimeMail('Log', "A gzipped copy of the log file for $ID is attached.", $properties, $copyFileName);
						}
						else {
							sendMimeMail('Log rotation', "The log file for $ID has been rotated. A gzipped copy remains on the server.", $properties, null);
						}
					}
					catch (Exception $zx) {
						echoIfVerbose('Exception when sending files: '.$zx->getMessage()." \n");
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
	doBeforeExit();	

	exit;


///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 


?>