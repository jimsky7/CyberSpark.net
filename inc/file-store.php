<?php
	/**
		CyberSpark.net monitoring-alerting system
		serialize/unserialize data to a file
	*/

function readStore($path, $base) {
	global $fileHandle;
	global $status;
	global $maxDataSize;
	
	if (isset($base) && (strlen($base)>0)) {
		$cleanBase = str_replace('/','-',$base);
	}
	else {
		$cleanBase = '';
	}

	if($fileHandle = @fopen("$path"."cyberspark/".$cleanBase."spiderlength.txt","r+")) {
		while (!feof($fileHandle)) {
			$status = unserialize(fread($fileHandle, $maxDataSize));	
		}
		fclose($fileHandle);
	}
}

function writeStore($path, $base) {
	global $results;
	global $fileHandle;

	if (isset($base) && (strlen($base)>0)) {
		$cleanBase = str_replace('/','-',$base);
	}
	else {
		$cleanBase = '';
	}

	if($fileHandle = fopen("$path"."cyberspark/".$cleanBase."spiderlength.txt","w+")) {
		rewind($fileHandle);
		fwrite($fileHandle, serialize($results));
		fclose($fileHandle);
	}
}




?>