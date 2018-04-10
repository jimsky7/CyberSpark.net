<?php
/**** 
	cs-lock-report.php
	Writes a text report of what URLs are currently locked (i.e. 'emails suppressed')

	Also cleans up any expired lock files.

****/

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net


include('cs-log-config.php');
include('cs-log-functions.php');
include('cs-log-pw.php');

define ('LOCKED_EXT', '.locked');
define ('LOCKED_DIR', 'locked/');		

$path 		 = substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
$lockedDir = $path . LOCKED_DIR;						// lock files go here

$now 	= time();	// current unix time in seconds
$sortOn	='url';		// sort the results by 'url'

function urlCaseComp($a, $b) {
	return strCaseCmp($a['url'], $b['url']);
}
function timeComp($a, $b) {
	return strCaseCmp($a['expires'], $b['expires']);
}

?>
<html>
<head>
	<meta charset="utf-8" />
</head>
<body style="font-family:'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', 'DejaVu Sans', Verdana, sans-serif;">
	<div style="margin-left:40px; width:80%;">
		<a href="http://cyberspark.net/">
		<img src="https://viz.cyberspark.net/a/images/CyberSpark-banner-320x55.png" width="300" height="50" alt="CyberSpark web site"/></a>
		&nbsp;<a href="/a/index.php"><img src="images/cyberspark-arrow-up-32x32.gif" width="32" height="32" alt="Analysis home page" /></a>
	</div>
	<div style="margin-left:40px; width:80%; padding-top:25px;">
	Email notifications for these URLs are currently suppressed:
	<br/>
<?php

if (file_exists($lockedDir)) {
	if ($dh = opendir($lockedDir)) {
		$a = array();
		while (($s = readdir($dh)) !== FALSE) {
			if ($s == '.') 
				continue;
			if ($s == '..') 
				continue;
			$hash = str_replace(LOCKED_EXT, '', $s);
			$filename = $lockedDir.'/'.$s;
			$s = file_get_contents($filename);
			$sa = unserialize($s);
			if ($sa['expires'] < $now) {
				echo "Lock on $sa[url] has expired.<br/>\n";
				unlink($filename);
				continue;
			}
			$su = str_replace(array('http://','https://'), '', $sa['url']);
			$a[$su] = array('expires'=>$sa['expires'], 'hash'=>$hash, 'url'=>$sa['url']);
		}
		closedir($dh);
		// Report will be sorted by URL.
		$result = uasort($a, (($sortOn=='url')?'urlCaseComp':'timeComp'));
		echo "<ul style='list-style-type:none'>\n";
		foreach($a as $url=>$value) {
			$es = hoursAndMinutes($value['expires']-$now);
			echo "<li>$url<br/><div style='font-size:smaller; margin-left:30px; margin-bottom:5px;'>($es remaining)\n";
			echo "<a href='https://".$_SERVER['SERVER_NAME']."/".CS_SUPPRESS_URL."?url=".$value['url']."&hash=".$value['hash']."' target='_blank' style='font-size:smaller;'>change</a></div></li>\n";
		}
		echo "</ul\n";
	}
	else {
		echo 'Unable to find the lock files.<br/>\n';
	}
}

?>
	</div>
</body>
</html>