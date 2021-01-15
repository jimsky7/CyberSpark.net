<?php
	/**
		CyberSpark.net monitoring-alerting system
		perform shutdown tasks
		received SIGINT or SIGKILL
		this includes sending 'destroy' to all filters
	*/

define ('NORMAL_EXIT', 0);

///////////////////////////////// 
function doBeforeExit() {
	global $storeFileName;
	global $store;
	global $pidFileName;
	global $heartbeatFileName;
	global $urlFileName;
	global $filters;
	global $properties;
	
	// For log transport
	global $pipes;
	global $ID;
	global $logTransportProcess;
	global $path;

	// DO ANYTHING THAT HAS TO BE DONE BEFORE EXITING
	
	// Let filters get a last crack at their private stores
	try {
		list($result, $message) = destroyFilters($properties, $filters, $store);
		if ($result != "OK") {
			echo "$result: \n$message\n";
		}
	}
	catch (Exception $x) {
	}
	
	// Write the store
	try {
		$store['cyberspark']['tripwire'] = false;	// signal that the shutdown was orderly
		$store['cyberspark']['notifiedtoday'] = $properties['notifiedtoday'];
		writeStore($storeFileName, $store);
	}
	catch (Exception $x) {
		echo "Warning: Unable to save data during shutdown.\n";
	}
	
	// Terminate log-transport process
	if ($logTransportProcess != null && $pipes != null) {
		echo "Shutting down $ID log transport. \n";
		try {
			@fclose($pipes[0]);			// note MUST do this or proc_terminate() may fail
			$LTpidFileName = $path . $ID . '-transport' . PID_EXT;
			//// First, send SIGINT to the log transport.
			//   Note that each transport is run using the PHP command line interpreter,
			//   so it is "enclosed" by an 'sh' shell. What we're doing first is sending
			//   the SIGINT to the PHP child, not to the 'sh' that surrounds it.
			if (file_exists($LTpidFileName)) {
				$pidNumber = file_get_contents($LTpidFileName, PID_FILESIZE_LIMIT);
				shell_exec ("kill -INT $pidNumber");	// terminate as if CTRL-C
			}
			// Pass on any output that came through a pipe from the child process
			if (isset($pipes[1])) {
				try {
					while (($line = fgets($pipes[1])) !== false) {
						echo $line;
					}
				}
				catch (Exception $x) {
				}
			}
			try {
				if (isset($pipes[1]) && ($pipes[2] != null)) {
					@fclose($pipes[1]);			// note MUST do this or proc_terminate() may fail
				}
				if (isset($pipes[2]) && ($pipes[2] != null)) {
					@fclose($pipes[2]);			// note MUST do this or proc_terminate() may fail
				}
			}
			catch (Exception $cx) {
				// Either of those may fail due to temporary conditions or previous
				// process failures, so just continue.
			}
			// Terminate the 'sh' process we launched
			//   Each of these has a child log transport process which has already been
			//   terminated (see the 'kill' above) and we have flushed the output pipe and
			//   echoed it to stdout as well.
			proc_terminate($logTransportProcess, SIGTERM);
			$i = 10;			// loop maximum of this many times
			while ($i-- > 0) {
				$status = proc_get_status($logTransportProcess);
				if ($status['running']) {
					usleep(SHUTDOWN_WAIT_TIME);	// wait if process has not stopped
				}
				else {
					break;			// process has stopped. Don't need to wait any more.
				}
			}
			// Formally close the process
			proc_close($logTransportProcess);
		}
		catch (Exception $txp) {
		}
	}

	// Delete any PID file
	try {
		@unlink($pidFileName);
	}
	catch (Exception $x) {
		echo "Warning: Unable to delete process ID 'pid' file.\n";
	}
	
	// Delete any HEARTBEAT file
	try {
		@unlink($heartbeatFileName);
	}
	catch (Exception $x) {
		echo "Warning: Unable to delete heartbeat file.\n";
	}

	// Delete any URL file
	try {
		@unlink($urlFileName);
	}
	catch (Exception $x) {
		echo "Warning: Unable to delete 'url' tracking file.\n";
	}

	// Close the log file
	endLog();
}

///////////////////////////////// 
function shutdownFunction($sig) {
	global $ID;
	
	if ($sig === SIGINT || $sig === SIGTERM) {
		writeLogAlert("Signal $sig received");
		echo "\nShutting down $ID [signal=$sig], please wait for confirmation below.\n";
	}
	doBeforeExit();
	echo "Shutdown confirmed.\n";
	exit;
}

///////////////////////////////////////////////////////////////////////////////////
function destroyFilters($properties, $filters, &$store) {
	
	$rankIndex	= 0;
	$top		= count($filters);
	$message	= "";
	$isOK		= true;
	
	echoIfVerbose("Begin shutting down ('destroy') filters\n");
	while ($rankIndex < $top) {
		$filterName = $filters[$rankIndex]->name;
		if (isset($filters[$rankIndex]->destroy)) {
			echoIfVerbose("[$filterName] " . $filters[$rankIndex]->destroy . "\n");
			// Create 'dummies' that will be the least harmful if the filter messes up
			$conditions  = array();
			$url         = '';
			$httpResult  = array();
			$elapsedTime = 0;
			$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
			try {
				list($mess, $result, $st) = call_user_func($filters[$rankIndex]->destroy, null, $filterArgs, $store[$filters[$rankIndex]->name]);
				if (isset($st)) {
					// Save this filter's private store
					$store[$filters[$rankIndex]->name] = $st;
				}
			}
			catch (Exception $x) {
				$result = "Exception";
				$mess = "Exception: " . $x->getMessage();
			}
			if (isset($mess) && isset($result)) {
				// If filter said anything other than "OK" then there's
				//   an overall failure.
				if ($result != "OK") {
					$isOK = false;
					$message .= "Filter [$filterName] did not shut down properly. ($mess)\n";
				}
				$isOK = $isOK && ($result == "OK");		// (both terms must be true)
			}
		}
		$rankIndex++;
	}
	if ($isOK) {
		$result  = "OK";
		$message = "";
	}
	else {
		$result = "Failed";
	}
	
	echoIfVerbose("Filters have been shut down.\n");

	return array($result, $message);

}


?>