<?php
	/**
		CyberSpark.net monitoring-alerting system
		maintain the scanning filters
	*/

include_once "include/classdefs.php";

/////////////////////////////////
// findAndAddFilters()
// Goes through the /filters directory and adds all complying scan filters
function findAndAddFilters($dirName, $properties) {

	// Look at the 'filters' directory - find all files
	// Add each one to the list
	if (isset($dirName)) {
		try {
			if ($handle = @opendir($dirName)) {
				// Make an array of things that the functions can use
				$setupArgs = array();
				if (isset($properties['id'])) {
					$setupArgs['id'] = $properties['id'];
				}
				// Run through all files in the directory, setting up functions
				while (($file = @readdir($handle)) !== false) {
					try {
							list($func, $extension) = explode(".", $file, 2);
							if (strlen($func) > 0) {
								echoIfVerbose("Attempting to add filter '" . $file . "'\n");	
								include_once($dirName . $file);
								// Execute the setup function for the filter
								if (function_exists($func)) {
									if (call_user_func($func, $setupArgs)) {
										echoIfVerbose("The filter '" . $func . "' was installed.\n");
									}
									else {
										echoIfVerbose("The filter '" . $func . "' failed to initialize.\n");
									}
						
						
								}
						}
					}
					catch (Exception $fx) {
echo "Couldn't install the filter from the file " . $dirName . $file . " exception was " . $fx->getMessage() . "\n";					
					}
				}
				closedir($handle);
			}
		}
		catch (Exception $x) {
echo "Exception in findAndAddFilters" . $x->getMessage() . "\n";
			return false;
		}
	}
	return true;
}

///////////////////////////////// 
function registerFilterHook($filterName, $filterType, $filterFunction, $rank) {
	global $filters;
	
	try {
//echo "Registering $filterName event '$filterType' rank $rank.\n";
		// All types of filter get a "rank" so that's what we use to
		// figure out how many filters exist already.
		$arraySize = count($filters);
//echo "Array size is $arraySize.\n";
		$slot = 0;
		// Find proper place to insert this filter based on rank (priority)
		while ($slot < $arraySize && $filters[$slot]->rank < $rank) {
			$slot++;
		}
		// Now $slot is where the thing is to be inserted could be one beyond end
		// Bump everything up one slot
		$top = $arraySize;
		$filters[$top] = new filter();	// temporarily insert new at the end to expand the array
//echo "Top is $top.\n";
		while ($top > $slot) {
			$filters[$top] = $filters[$top-1];
			$top--;
		}
		// "$slot" remains set properly for the following insertions
		/// Save all for this slot
		$filters[$slot] = new filter();
		$filters[$slot]->name = $filterName;
		$filters[$slot]->rank = $rank;
//echo "Registering $filterName in position $slot\n";
		if ($filterType == 'scan') {
			$filters[$slot]->scan = $filterFunction;		
		}
		elseif ($filterType == 'init') {
			$filters[$slot]->init = $filterFunction;		
		}
		elseif ($filterType == 'destroy') {
			$filters[$slot]->destroy = $filterFunction;		
		}
//echo "Registered $filterName of type '$filterType' and rank $rank.\n";
//echo print_r($filters);
		return true;
	}
	catch (Exception $x) {
		return false;
	}
}


?>