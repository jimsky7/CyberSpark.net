<?php
	/**** cs-lock-handler-core.php
		
		Common portion of lock handler.
		Include this in other files.

		Outer file must include these definitions, adjusted for path if necessary:
			//	include('cs-log-config.php');
			//	include('cs-log-functions.php');
			//	include('cs-log-pw.php');
			//	define ('LOCKED_EXT', '.locked');
			//	define ('LOCKED_DIR', 'locked/');

	****/

include('cs-log-config.php');
include('cs-log-functions.php');
include('cs-log-pw.php');

define ('LOCKED_EXT', '.locked');
define ('LOCKED_DIR', 'locked/');		

function lessThanHours($seconds) {
	return (int)(($seconds/(60*60))+0.999).' hours or less ';
}

// In 'automated' mode responses are terse, like 'OK' 'LOCKED' 'GONE'
// In 'human' mode responses are explained
$automated = true;			// 'true' for terse response, 'false' for wordy

// Authentication (it's minimal, but at least it's something) token
// POST Parameter CS_API_KEY must match an entry in $CS_API_KEYS (if defined)
if (isset($CS_API_KEYS)) {
	// Does param CS_API_KEY exist?
	if (!isset($_POST['CS_API_KEY'])) {
		header($_SERVER['SERVER_PROTOCOL'].' 400 Bad request', true, 400);
		echo "All required parameters must be supplied. [1]\n";
		exit;
	}

	if (isset($_POST['CS_API_KEY'])) {
		$API_KEY = $_POST['CS_API_KEY'];
	}
	
	// If referrer is our own form on THIS server, then fake the key (if one is needed)
	if (isset($_SERVER['HTTP_REFERER']) && isset($_SERVER['SERVER_NAME'])) {
		$selfServer = $_SERVER['SERVER_NAME'];										// like www.example.com
		$referringURL = str_replace('https://', '', $_SERVER['HTTP_REFERER']);		// like www.example.com/foo
		if (strncasecmp($referringURL, $selfServer, strlen($selfServer)) == 0) {
			// Referred from this same server
			if (strpos($referringURL, CS_SUPPRESS_URL) >= 0) {
				// Same server and the proper script
				$API_KEY = $CS_API_KEYS[0];		// just use the first valid key
				$automated = false;
			}
		}
	}

	// Does param match? (strict comparison)
	if (($API_KEY==null) || !in_array($API_KEY, $CS_API_KEYS, true)) {
		header($_SERVER['SERVER_PROTOCOL'].' 401 Not Authorized', true, 401);
		echo "Your authentication failed.\n";
//	echo "Request method: ".$_SERVER['REQUEST_METHOD']."<br/>\n";
//	echo "API_KEY: ".$API_KEY."<br/>\n";
//	print_r($_SERVER);
		exit;
	}
}

// POST item "md5_url" is required
if (!isset($_POST['md5_url'])) {
	header($_SERVER['SERVER_PROTOCOL'].' 400 Bad request', true, 400);
	echo "All required parameters must be supplied. [2]\n";
	exit;
}

$md5URL = $_POST['md5_url'];
$hours = 0;
if (isset($_POST['hours'])) {
	$hours	= $_POST['hours'];
}

$url = '(none given)';
if (isset($_POST['url'])) {
	$url = $_POST['url'];
}

// Note on __FILE__
// If this cs-lock-handler-core.php is INCLUDED by another file in another directory, 
// the __FILE__ value is based on where this file lives, not on where the includING
// file is. So the includING file might be up at docroot, and this files might be in 
// docroot/a/ but __FILE__ will give docroot/a/ as its location.
//
$path 		 = substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
$lockedDir = $path . LOCKED_DIR;						// lock files go here

if (!file_exists($lockedDir)) {
	// Directory doesn't exist, create it
	if (!mkdir($lockedDir, 0755)) {
		header($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden', true, 403);
		echo "Unable to create a directory ($lockedDir).\n";
		exit;
	}
}

$filename = $lockedDir.$md5URL.LOCKED_EXT;

///////////////////////////////////////////////////////////////////////////////////
// If hours==0 then this is an inquiry
if ($hours == 0) {
	$s = @file_get_contents($filename);
	if ($s === FALSE) {
		// Lock file doesn't exist. URL is not locked.
		if ($automated) {
			echo 'OK';
		}
		else {
			echo "Email alerts are active for $url <br/>\n";
		}
		exit;
	}
	// Lock file exists for this URL.
	$now = time();
	$expires = (int)$s;
	if (($expires-$now) < 0) {
		// Lock has expired, delete the file and return a 410 GONE result
		@unlink($filename);
		if ($automated) {
			header($_SERVER['SERVER_PROTOCOL'].' 410 Gone', true, 410);
			echo 'GONE';
		}
		else {
			echo "Email alerts are active for $url <br/>\n";
		}
		exit;
	}
	// Otherwise, file exists and is unexpired, so the result is 'LOCKED'
	if ($automated) {
		echo 'LOCKED ('.($expires-$now).' seconds remaining)';
	}
	else {
		echo "Email alerts are suspended for $url <br/>\n";
		echo '('.lessThanHours($expires-$now)." remaining)<br/>\n";
	}
	exit;
}

///////////////////////////////////////////////////////////////////////////////////
// If hours > 0 then set suppression lock
if ($hours > 0) {
	$now = time();
	$expires = $now + ($hours*60*60);
	$result = @file_put_contents($filename, (string)$expires);
	if ($result === FALSE) {
		header($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden', true, 403);
		echo "Failed to create a lock file. ($filename)\n";
		exit;
	}
	if ($automated) {
		echo 'LOCKED ('.($expires-$now).' seconds remaining)';
	}
	else {
		echo "Email alerts are now suspended for $url <br/>\n";
		echo '('.lessThanHours($expires-$now)." remaining)<br/>\n";
	}
	exit;
}

///////////////////////////////////////////////////////////////////////////////////
// If hours < 0 then unset the lock
if ($hours < 0) {
	unlink($filename);
	if ($automated) {
		header($_SERVER['SERVER_PROTOCOL'].' 410 Gone', true, 410);
		echo 'GONE';
	}
	else {
		echo "Email alerts will now resume for $url <br/>\n";
	}
	exit;
}

echo 'FAILED';
exit;