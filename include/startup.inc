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
			// Create 'dummies' that will be the least harmful if the filter messes up
			$conditions  = array();
			$url         = '';
			$httpResult  = array();
			$elapsedTime = 0;
			$filterArgs = setFilterArgs($properties, $httpResult, $url, $conditions, $elapsedTime);
			try {
				// Ensure there's a 'store' defined for every filter
				if (!isset($store[$filters[$rankIndex]->name])) {
					$store[$filters[$rankIndex]->name] = array('cyberspark'=>'(initialized by cyberspark)');
				}
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