<?php
/**** cs-log-get-url-from-hash.php

	Param:
		?URL_HASH=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
		
	Result:
		The URL corresponding to the hash
	
// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** http://php.net/manual/en/class.mysqli.php
// **** http://d3js.org/

****/

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Setup

include('cs-log-config.php');

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// Parameters
$URL_HASH = ifGetOrPost('URL_HASH');
$API_KEY = ifGetOrPost('API_KEY');

// Validate 'API_KEY'

// >>>



if ($URL_HASH == null) {
	die("Required parameters are missing or incorrect");
}

$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);

if ($mysqli == null) {
	die("Couldn't connect to MySQL on ".MYSQL_HOST." with user name and password specified.");
}

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
//	Retrieve URL corresponding to a CS hash

$query = "SELECT `url` FROM `urls` WHERE (`URL_HASH`=?)";
$stmt =  $mysqli->stmt_init();
$result = $stmt->prepare($query);
$result = $stmt->bind_param('s', $URL_HASH);
$result = $stmt->execute();
if ($stmt->errno) {
	echo "Error [alert] number ".$stmt->errno." <br/>\r\n";
	echo "Error [alert] message ".$stmt->error." <br/>\r\n";
	die ("Program ended.");
}
$result = $stmt->bind_result($url) ;
$result = $stmt->fetch();
if ($result) {
	header('Content-type: text/plain');
	echo $url;
}
$stmt->close();

// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
// All done

$mysqli->close();

?>