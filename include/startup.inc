<?php
	/**
		CyberSpark.net monitoring-alerting system
		perform startup tasks
		perform init tasks on all filters
	*/

///////////////////////////////////////////////////////////////////////////////////
function initFilters($filters, $properties, &$store) {
	$rankIndex	= 0;
	$top		= count($filters);
	$message	= "";
	$isOK		= true;
	
	echoIfVerbose("Begin initializing filters\n");
	while ($rankIndex < $top) {
		$filterName = $filters[$rankIndex]->name;
		if (isset($filters[$rankIndex]->init)) {
			echoIfVerbose("[$filterName] " . $filters[$rankIndex]->init . "\n");
			$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
			try {
				list($mess, $result, $st) = call_user_func($filters[$rankIndex]->init, null, $filterArgs, $store[$filters[$rankIndex]->name]);
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
					$message .= "Filter [$filterName] did not initialize properly. ($mess)\n";
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
	
	echoIfVerbose("Filters have been initialized.\n");

	return array($result, $message);

}

///////////////////////////////////////////////////////////////////////////////////
function performInitTasks() {
	initFilters();
}
?>