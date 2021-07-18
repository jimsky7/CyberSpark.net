<?php
	/**
		CyberSpark.net monitoring-alerting system
		serialize/unserialize data to a file
	*/

function readStore($storeFileName, $maxDataSize) {
	try {
		$store = unserialize(@file_get_contents($storeFileName, $maxDataSize));	
	}
	catch (Exception $x) {
		echo 'Exception in readStore(): ' . $x->getMessage() . "\n";
		$store = null;
	}
	return $store;
}

function writeStore($storeFileName, $store) {
	try {
		file_put_contents($storeFileName, serialize($store));
	}
	catch (Exception $x) {
		echo 'Exception in writeStore(): ' . $x->getMessage() . "\n";
	}
}

?>