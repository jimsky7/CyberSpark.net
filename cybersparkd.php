#!/usr/bin/php -q
<?php
	/**
		CyberSpark.net monitoring-alerting system
		called from the command line as
		  /usr/local/cyberspark/cybersparkd.php --arg --arg
	*/
	/**
		Put a script at /etc/init.d/cyberspark
		to start this file you are reading, 
		and this one will start individual "sniffers" (monitors).
		TO START UPON REBOOT
		  update-rc.d cyberspark defaults
		TO REMOVE SO IT DOESN'T START ANY MORE
		  update-rc.d -f cyberspark remove
		OR, if you have it installed, you can use 
		  rcconf
		to mark this service for restart, or not.
	**/
	
///////////////////////////////// 
// include supporting code
///////////////////////////////// 
// Local (your installation) variables, definitions, declarations
include_once "cyberspark.config.php";
// CyberSpark system variables, definitions, declarations
include_once "cyberspark.sysdefs.php";

// Other supporting code
include_once "include/args.inc";
include_once "include/mail.inc";
declare(ticks = 1);					// allows shutdown functions to work

///////////////////////////////// 
// 
$ID 		= INSTANCE_ID;		// example "CS8"
$propsFileName= "";				// exact name of the properties file
$isDaemon   = true;				// true if running in 'daemon' mode
$configTest = false;            // run in TEST mode- just read properties
$running	= false;
$time		= 1;				// default is to run every 1 minutes
$loop		= 0;
$subID		= 0;
$process	= null;
$pipes		= null;				// array containing pipes for all child processes we launch
$path		= APP_PATH;			// get from config - this will be a local copy
$propsDir	= PROPS_DIR;		// get from config - this will be a local copy
	
$from 			= EMAIL_FROM;						// (from config)
$to				= EMAIL_TO;							// (from config)
$replyTo		= EMAIL_REPLYTO;					// (from config)
$abuseTo		= EMAIL_ABUSETO;					// (from config)
$administrator	= EMAIL_ADMINISTRATOR;				// (from config)
$sleepTime 		= KEEPALIVE_LOOP_SLEEP_TIME;		// (from config)

$isDaemon		= false;				// required by args.inc but not used herein
$configTest		= false;				// required by args.inc but not used herein

///////////////////////////////// 
// Initialization
// Get the filesystem path to this file (only the PATH) including an ending "/"
// NOTE: This overrides the APP_PATH from the config file, which will be unused.
$path 		= substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
//$propsDir	= $path . $propsDir;
//$scriptName	= $argv[0];

/** Parse the command-line arguments **/
getArgs($argv);
// $ID and some other things may have been changed at this point
//     because they're command-line args.
	
//$propsFileName	= $propsDir . $ID . PROPS_EXT;
$pidFileName		= $path . $ID . PID_EXT;
$heartbeatParent	= $path . $ID . HEARTBEAT_EXT;
$startupFileName    = $path . '/' . STARTUP_MESSAGE_FILE; 
$running 			= true;
$notified			= false;		// daily notification sent?

$timeStamp = date("r");

///////////////////////////////// 
// Register shutdown functions.
// These will be called when certain SIGnals are received by this process.
// Other SIGnals are not caught and processed and no action is taken when they are
// received (other than perhaps our being shut down very un-gracefully).
// Some operating systems may not allow us to catch these ... Ubuntu definitely DOES.
try {
	pcntl_signal(SIGTERM, 'shutdownProcesses');		// kill
	pcntl_signal(SIGINT,  'shutdownProcesses');		// Ctrl-C
}
catch (Exception $x) {
	echo "Warning: Unable to register shutdown functions. $timeStamp\n";
}
	
///////////////////////////////// 
// Look for a "power failure" startup message left by /etc/init.d/cyberspark
// Send an email to administrator.
// Delete the file.
$startupMessage = @file_get_contents($startupFileName);
if ($startupMessage !== false) {
	$timeStamp = date("r");
	$subject = "$ID recovered from powerfail $timeStamp";
	$message = "$ID recovered from powerfail $timeStamp";
	textMail($administrator, $from, $replyTo, $abuseTo, $subject, $message, SMTP_SERVER, SMTP_PORT, SMTP_USER, SMTP_PASSWORD);
	@unlink($startupFileName);
}

///////////////////////////////// 
// Write process ID to a file.  This is for this monitoring this daemon only.
// For example, /etc/init.d/cyberspark looks for this file at various times.
try {
	@unlink($heartbeatParent);		// this file contains "current time" as a heartbeat
	@unlink($pidFileName);			// this file contains "our" process ID number
}
catch (Exception $x) {
	echo "Warning: Unable to delete cybersparkd's process ID (or heartbeat) file(s). $timeStamp Exception: ".$x->getMessage()."\n";
}
try {
	// This writes this process's ID out to the pid file
	@file_put_contents($pidFileName, (string)posix_getpid());	// save process ID
}
catch (Exception $x) {
	echo "Warning: Failed to write cybersparkd's process ID to a 'pid' file. $timeStamp Exception: ".$x->getMessage()."\n";
}

///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// Find the properties files and launch a monitoring instance for each one
$subID = 0;
while (file_exists($propsDir . "/$ID-$subID" . PROPS_EXT)) {
	$timeStamp = date("r");
	echo "$ID is launching $ID-$subID - $timeStamp\n";	
	$descriptorspec = array(
  		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
  		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
  		2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
	);	
	// Launch a child process corresponding to this properties file.
	// Actually a 'sh' gets launched, and that in turn runs 'php' which picks up the
	//   cyberspark.php script that does the actual monitoring.  So there are two levels
	//   of child proces below us now.  First is the 'sh' and below that a 'php' instance.
	//   The 'php' instance of cyberspark.php records its process id in a 'pid' file.
	// Later on we will first use the process ID from the 'pid' file to kill the 'php'
	//   process.  Then when that terminates, we read any stdout it has produced, and we
	//   terminate the 'sh' as well.
	$process[$subID] = proc_open("php $path/cyberspark.php --id $ID-$subID --daemon", $descriptorspec, $pipes[$subID]);
	$alertCount[$subID] = 0;	// initialize the "alert" count to zero - sends emails if this process disappears
	$subID++;					// ready to look for next properties file
}	
$subID--;						// back off by one to be accurate with end of the array
	
///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////

// At this point an instance (of cyberspark.php) has been launched for each
//   properties file that was found.  Now this parent process turns into a daemon and just
//   sits and watches those child processes.
// Suggestions:
//   It could send an email to the administrator if a process fails (already written, see below).
//   It could restart the failing process, unless it fails too many times (written, see below).

$timeStamp = date("r");
$subject = "$ID cybersparkd launched $timeStamp";
$message = "$ID cybersparkd launched $timeStamp";
textMail($administrator, $from, $replyTo, $abuseTo, $subject, $message, SMTP_SERVER, SMTP_PORT, SMTP_USER, SMTP_PASSWORD);

///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// Loop until someone shuts this script down with SIGINT or SIGTERM
// If this script fails, there is no safety net. If you modify, please be sure to use
// 'try' to enclose any new risky operations that might fail. If you leak memory or do
// anything else that might shut own PHP, then you're on your own. This script has been
// known to run for many months without failing in an Ubuntu environment.
//
while ($running) {		
	///////////////////////////////////////////////////////////////////////////
	// Save a heartbeat file for this, the parent, process
	// You may well ask "who is monitoring this file?" and the answer is "nobody, for now."
	// If you wanted to get sophisticated, you could create another script and 'cron' it
	// so it would check the heartbeat periodically.
	$timeStamp = date("r");
	try {
		$heartbeatTime  = time();	// current time in seconds (Unix time)
		$heartbeatTime += $sleepTime*3;		// use sleep time x3 as time someone should call for help
		@file_put_contents($heartbeatParent, "$heartbeatTime");	// save next run time
//      echo "Writing heartbeat $heartbeatTime in $heartbeatParent\n";
	}
	catch (Exception $x) {
		echo "Warning: $ID was unable to write a 'heartbeat' file for myself. $heartbeatParent : $timeStamp\n";
	}

	///////////////////////////////////////////////////////////////////////////
	// Report in once a day to say "I'm OK"
	$dateString = date("r");
	if (strpos(date("r"), (' '.DEFAULT_NOTIFY_HOUR.':')) !== false) {
		if (!$notified) {
			$subject = "$ID daemon OK $timeStamp";
			$message = "$ID (the parent) daemon reports that it's running.";
			textMail($administrator, $from, $replyTo, $abuseTo, $subject, $message, SMTP_SERVER, SMTP_PORT, SMTP_USER, SMTP_PASSWORD);
			$notified = true;
		}
	}
	else {
		$notified = false;
	}
	
	///////////////////////////////////////////////////////////////////////////
	// Sleep a while
	$sleepResult = sleep($sleepTime);		// sleep (seconds)
	$loopStartTime = time();				// seconds (Unix time)
	$loop++;

	///////////////////////////////////////////////////////////////////////////
	// Check on child processes and report if any disappear
	// These are the 'sh' processes, not the cyberspark.php processes
	$i = 0;
	while (($i <= $subID) && $running) {
		$timeStamp = date("r");
		try {
			$status = proc_get_status($process[$i]);
		
			if	($status['running']) {
				
				// Check 'heartbeat' to see if child process is possibly stalled.
				// Here we are talking about 'heartbeat' files written by the child processes.
				// Each one writes and then updates its heartbeat file each time it goes around
				// its main loop. This timing differs for each child process, and is set within
				// its own properties file.
				try {
					$heartbeatFileName	= $path . "$ID-$i" . HEARTBEAT_EXT;
					$heartbeatContents = @file_get_contents($heartbeatFileName);	// get predicted run time
					list($heartbeatTime) = sscanf($heartbeatContents, "%d");
					if ($loopStartTime > ($heartbeatTime+HEARTBEAT_LATE)) {
						// The process hasn't updated its heartbeat file recently - might be stalled
						echo "$ID-$i unresponsive. Predicted=$heartbeatTime Current=$loopStartTime $timeStamp \n";
						$subject = "$ID-$i Unresponsive $timeStamp";
						$message = "$ID reports $ID-$i is unresponsive. Predicted=$heartbeatTime Current time=$loopStartTime\n";
						// Report which URL is being sniffed by the process
						try {
							$urlFileName	= $path . "$ID-$i" . URL_EXT;
							$currentURL = @file_get_contents($urlFileName, MAX_URL_LENGTH);
							$message .= "$ID-$i was last processing this URL: $currentURL\n";
						}
						catch (Exception $curlf) {
						}
						$remainingTime = 0;
						if (RESTART_ON_FAILURE) {
							$blue = HEARTBEAT_BLUE;
							$blueLate = HEARTBEAT_LATE;
							$lateSeconds = $loopStartTime - $heartbeatTime;
							$remainingTime = $blue - ($lateSeconds);
							$message .= "$ID-$i has been unresponsive for $lateSeconds seconds (".floor($lateSeconds/60)." minutes).\n";
							if ($remainingTime > 0 ) {
								$message .= "$ID-$i will be terminated in $remainingTime seconds.\n";
							}
							echo "$ID-$i will be terminated in $remainingTime seconds. $timeStamp\n";
						}
						if ($remainingTime < 0 ) {
							$subject = "$ID-$i Terminated (was unresponsive) $timeStamp";
							$message .= "$ID-$i is being terminated NOW.\n";
							echo "$ID-$i is being terminated NOW. $timeStamp\n";
							// Remember there are two layers below this daemon.
							// The topmost is a PHP process that launched the monitor (the child)
							// The monitor itself is a child of that PHP process, and has written
							//   its PID and its heartbeat information into files.
							// Let the top PHP process keep running - it will detect that its child
							//   process has been terminated and it will also terminate.
							// First, read 'Child' PID from file
							$pfn = "$path/$ID-$i". PID_EXT;
							$childPidString = '';
							if (file_exists($pfn)) {
								try {
									$pidNumber = file_get_contents($pfn, PID_FILESIZE_LIMIT);
								}
								catch (Exception $x) {
									$message .= "Couldn't read the PID file $pfn. Error: ".$x->getMessage()."\n";
									echo "Couldn't read the PID file $pfn. $timeStamp Error: ".$x->getMessage()."\n";
								}
								// Kill the monitor process;
								if (strlen($pidNumber) > 0) {
									echo "$ID-$i is being terminated with 'kill -KILL $pidNumber' - $timeStamp\n";
									$message .= "$ID-$i is being terminated.\n";
									shell_exec ("kill -KILL $pidNumber");	// terminate as if CTRL-C ... this is "graceful"
								}
								// When the monitor is restarted (next time around the loop) it will
								// remove its PID file "$ID-$i.pid" and its heartbeat file "$ID-$i.next";
							}
							else {
								$message .= "$ID-$i There's no PID file for the child process.\nYou'll have to deal with this manually.\n";
								echo "$ID-$i There's no PID file for the child process.\nYou'll have to deal with this manually. $timeStamp\n";
							}
							// You only want to do this after a SIGNIFICANT TIME has passed - say 30 to 60 minutes. Or number of notifications.
							// Because processes can go unresponsive just because the sites they're monitoring all get very slow...so you should wait
							//   enough minutes that the maximum number of GET timeouts could be completely processed.  For example, if you're monitoring
							//   20 URLs and they all became slow, then it could take 20 minutes or more to go around a loop, and the child process
							//   might be unresponsive that entire time and yet be functioning perfectly. It would just be updating its heartbeat
							//   much more slowly than usual.
						}
						// Send out mail describing the surgery
						textMail($administrator, $from, $replyTo, $abuseTo, $subject, $message, SMTP_SERVER, SMTP_PORT, SMTP_USER, SMTP_PASSWORD);
						$i++;
						continue;
					}
				}				
				catch (Exception $x) {
					echo "Critical: $ID unable to read 'heartbeat' file. $heartbeatFileName $timeStamp\n";
					$subject = "$ID-$i Failure/Critical $timeStamp";
					$message = "Critical: $ID unable to read 'heartbeat' file. $heartbeatFileName\n";
					textMail($administrator, $from, $replyTo, $abuseTo, $subject, $message, SMTP_SERVER, SMTP_PORT, SMTP_USER, SMTP_PASSWORD);
					$i++;
					continue;
				}
				
				// OK, everything is fine
				$alertCount[$i] = 0;	// reset alerts because process is running fine
			}
			else {
				// A process is no longer running!
				
				// CHILD PROCESS IS NO LONGER RUNNING
				
				// Send some email alerts to the administrator.
				try {
					$subject = "$ID-$i Failure/Restart - $timeStamp";
					echo "$subject\n";
					$message = "$ID reports that $ID-$i has failed.\n";

					$propsExist = file_exists("$propsDir$ID-$i" . PROPS_EXT);

					if (RESTART_ON_FAILURE && $propsExist) {
						$message .= "$ID is preparing to restart $ID-$i.\n";
					}
					if (!$propsExist) {
						$message .= "$ID-$i properties file has disappeared.\n";
						echo "$ID-$i properties file has disappeared. $ID-$i ended. $timeStamp\n";
						$alertCount[$i] = FAILURE_ALERT_MAX;
					}
					if ($alertCount[$i] == FAILURE_ALERT_MAX) {
						$message .= "\nThis is the last (or only) alert you will receive about this condition, unless you restart the process and it fails again.\n";
					}					
					// Report which URL was being sniffed by the process
					try {
						$urlFileName	= $path . "$ID-$i" . URL_EXT;
						$currentURL = @file_get_contents($urlFileName, MAX_URL_LENGTH);
						$message .= "$ID-$i was last processing this URL: $currentURL\n";
					}
					catch (Exception $curlf) {
					}
					// Attempt to restart?  This can be set (defined) in the config file
					if (RESTART_ON_FAILURE && $propsExist) {
						echo "$ID Restarting $ID-$i - $timeStamp\n";
						try {
							$descriptorspec = array(
  								0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
  								1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
  								2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
							);	
							$process[$i] = proc_open("php $path/cyberspark.php --id $ID-$i --daemon", $descriptorspec, $pipes[$i]);
							$alertCount[$i] = 0;	// initialize the "alert" count to zero - sends emails if this process disappears
							$message .= "$ID-$i has been restarted.\n";
						}
						catch (Exception $x) {
							echo "$ID Failed to restart $ID-$i - $timeStamp Exception: " . $x->getMessage() . "\n";
							$message .= "The attempt failed. Exception: " . $x->getMessage() . "\n";
						}
					}
					if ($alertCount[$i] <= FAILURE_ALERT_MAX) {
						textMail($administrator, $from, $replyTo, $abuseTo, $subject, $message, SMTP_SERVER, SMTP_PORT, SMTP_USER, SMTP_PASSWORD);
					}
				}
				catch (Exception $x) {
				}

				
				// YOU COULD ADD SOMETHING HERE ABOVE
				///////////////////////////////////////////////////////////////////
					
				$alertCount[$i]++;
			}
		}
		catch (Exception $x) {
		}
		$i++;
	}
}

///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// Clean up when this script is just exiting normally. This could happen, for instance,
// if it is executed from the command line and was not told to run as a daemon.
shutdownProcesses();
	
// and end
exit;


///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
function shutdownProcesses() {

	//// The shutdown process is fully of snakes and traps. See notes. This is based on,
	//   and operates on Ubuntu versions 8.04, 10.04 and 12.04 LTS. We can't vouch for 
	//   other systems.
	//   The array $process[] contains the information on the child processes, but there's
	//   also $pipes[] that contains the stdout piped information from each child. We have
	//   to deal properly with those or the child won't shut down properly. So pay attention.
	//// Because shutdownProcesses() is a 'callback' executed when certain SIGnals are
	//   received, we have to use PHP globals to access pertinent information.
	global $ID;
	global $subID;
	global $process;
	global $pipes;
	global $pidFileName;
	global $heartbeatParent;
	global $path;
	global $running;
	
	// Note that any 'echo' in this code goes into stdout and if you use
	//   service cyberspark stop
	// you will see it on console or in your shell.
	$timeStamp = date('r');
	echo "\nPlease wait while the monitors (the child processes) shut down. $timeStamp \n";	
	$running = false;
	try {
		while ($subID >= 0) {
			@fclose($pipes[$subID][0]);			// note MUST do this or proc_terminate() may fail
			$pfn = "$path/$ID-$subID.pid";
			//// First, send SIGINT to the actual PHP sniffer.
			//   Note that each PHP sniffer is run using the PHP command line interpreter,
			//   so it is "enclosed" by an 'sh' shell. What we're doing first is sending
			//   the SIGINT to the PHP child, not to the 'sh' that surrounds it.
			if (file_exists($pfn)) {
				$pidNumber = file_get_contents($pfn, PID_FILESIZE_LIMIT);
				shell_exec ("kill -INT $pidNumber");	// terminate as if CTRL-C
			}
			// Pass on any output that came through a pipe from the child process
			if (isset($pipes[$subID][1])) {
				try {
					while (($line = fgets($pipes[$subID][1])) !== false) {
						echo $line;
					}
				}
				catch (Exception $x) {
				}
			}
			@fclose($pipes[$subID][1]);		// note MUST do this or proc_terminate() may fail
			@fclose($pipes[$subID][2]);		// note MUST do this or proc_terminate() may fail

//			$status = proc_get_status($process[$subID]);
			// Terminate the 'sh' process we launched
			//   Each of these has a child process cyberspark.php which has already been
			//   terminated (see the 'kill' above) and we have flushed the output pipe and
			//   echoed it to stdout as well.
			proc_terminate($process[$subID], SIGTERM);

			$i = 10;			// loop maximum of this many times
			while ($i-- > 0) {
				$status = proc_get_status($process[$subID]);
				if ($status['running']) {
					usleep(SHUTDOWN_WAIT_TIME);	// wait if process has not stopped
				}
				else {
					break;			// process has stopped. Don't need to wait any more.
				}
			}
			// Formally close the process
			proc_close($process[$subID]);
			$subID--;
		}
	}
	catch (Exception $x) {
		echo "\n  Exception: " . $x->getMessage() . "\n";	
	}
	$timeStamp = date('r');
	echo "\nAll monitors (children of this process) have been shut down. $timeStamp\n";	
	
	try {
		@unlink($pidFileName);
		echo "Removed my PID file.\n";	
	}
	catch (Exception $x) {
	}
	try {
		@unlink($heartbeatParent);
		echo "Removed my heartbeat file.\n";	
	}
	catch (Exception $x) {
	}

	exit;
}

?>