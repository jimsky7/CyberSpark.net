<?php
/**** 
	Capture a log entry
****/

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/
include('cs-log-config.php');

// >>> For debugging
// >>> Write the incoming data to a file
$fn = '/var/www/slice/_ups.log';
clearstatcache(false, $fn);
$length = filesize($fn);
$f = fopen($fn, 'a');
if (($length == 0) && isset($_POST['header'])) {
	fwrite($f, (trim($_POST['header'])."\n"));
}
fwrite($f, (trim($_POST['log'])."\n"));
fclose($f);

// >>> For production
// >>> Add the log entry to the database


?>