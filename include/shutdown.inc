<?php
	/**
		CyberSpark.net monitoring-alerting system
		perform shutdown tasks
		received SIGINT or SIGKILL
		this includes sending 'destroy' to all filters
	*/

///////////////////////////////// 
function doBeforeExit() {
	global $storeFileName;
	global $store;
	global $pidFileName;
	global $heartbeatFileName;
	global $urlFileName;
	global $filters;
	global $properties;
	
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
		echo "\nShutting down $ID [signal=$sig], please wait for confirmation message...\n";
		doBeforeExit();
		echo "Shutdown confirmed.\n";
		exit;
	}
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