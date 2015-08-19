<?php
	/**
		CyberSpark.net monitoring-alerting system
		some general utility functions used in filters
	*/


///////////////////////////////// 
// Number of lengths allowed by the limitLengths() function
if (!defined('MAX_LENGTHS')) {
	define  ('MAX_LENGTHS', 50);
}

///////////////////////////////// 
// Limit a comma-delimited string to a certain number of values
function limitLengths($s, $limit) {
	$lengths = explode(',', $s);
	$c = count($lengths);
	if ($c > $limit) {
		$c = $c - $limit;
		for ($i = 0; $i < $c; $i++) {
			unset($lengths[$i]);
		}
		$s = '';
		foreach ($lengths as $length) {
			$s .= ",$length";
		}
		$s = trim($s, ",");
	}	
	// Return modified (reconstructed) string 
	//   plus an array conaining lengths
	return array($s, $lengths);	
}

?>