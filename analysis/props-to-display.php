<?php
/**** Read properties files and convert to a sorted CSV for later analysis ****/

/*
	This file must be located at /analysis/ relative to server docroot.
	Properties files may be anywhere.
	System config and sysdefs need to be one level up, at server docroot.
	
	For security, follow these rules:
	
	* Never install this on a CyberSpark monitoring ("sniffer") server. There should
	  never be a web server running on that type of server anyway!
	* Create .htaccess or server rules so that the directory containing the properties
	  files cannot be served by the web server.
	  
	Parameters
	
	//////////
	base=relative_directory
	
		This is relative to directory this script is in.
		For example, if the script is in /analysis/ and the properties are in /analysis/properties/
		  then use
		http://slice.red7.com/analysis/props-to-display.php?base=properties

	//////////
*/

///////////////////////////////// 
// include supporting code
///////////////////////////////// 
// CyberSpark system variables, definitions, declarations
// include_once "../cyberspark.sysdefs.php";
include_once "props-to-functions.php";

///////////////////////////////// 
// 
$propsFileName= "";				// exact name of the properties file
//	$path		= APP_PATH;			// get from config - this will be a local copy
//	$propsDir	= PROPS_DIR;		// get from config - this will be a local copy
	

///////////////////////////////// 
// Initialization
// Get the filesystem path to this file (only the PATH) including an ending "/"
// NOTE: This overrides the APP_PATH from the config file, which will be unused.
$path 		= substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"

?>
<HTML>
<HEAD>
  <STYLE TYPE='text/css'>.RED_ALERT { color:#FF0000; font-weight:bold; } </STYLE>
  <STYLE TYPE='text/css'>.TABLE_ROW { border-top:thin; border-top-style:solid; border-top-color:#888888; border-top-width:1px; } </STYLE>
  <STYLE TYPE='text/css'>.LOWER_LINE { font-size:12px; } </STYLE>
</HEAD>
<BODY style='font-family:Arial;'>
<p style="font-size:22px;"><a href="http://cyberspark.net/"><img src="images/CyberSpark-banner-320x55.png" width="300" height="50" alt="CyberSpark web site"/></a><a href='index.php'><img src="images/uparrow.jpg" width="52" height="48" alt="Analysis home page"/></a>  </p>
<?php
if (!isset($_GET['base']) || (strlen($_GET['base']) == 0)) {
	echo "Requires ?base=/dir/";
	exit;
}
$propsDir = $path . $_GET['base'];
if ($propsDir[strlen($propsDir)-1] != '/') {
	$propsDir = $propsDir . '/';
	$propsDir = str_replace('//', '/', $propsDir);
}
// echo "Base is: $propsDir <br/>\r\n";

///////////////////////////////// 
// Enumerate the files in the properties directory, sucking up info
// It's all going into arrays in mem, so there is some practical limit here.

if (!is_dir($propsDir)) {
	echo "<span class='RED_ALERT'>$propsDir is not a directory</span><br/>\r\n";
	exit;
}

$totalFiles = 0;
$data = array();
$dirContents = dir($propsDir);
while (($entry = $dirContents->read()) !== false) {
	// Get an entry from the directory 
	$thisEntry = $propsDir . $entry;
	// Look for '.' or '..' and ignore these entries
	if ((strcmp('.', $entry)==0) || (strcmp('..', $entry)==0)) {
//		echo "Skipping: $entry<br/>\r\n";
		continue;
	}
	else if (is_link($thisEntry)) {
		// Skip a 'link' (not directory, not file) avoids recursion, but might
		// miss something that you want to examine. You can always set up separate
		// scan that uses 'base=' to target the actual directory you want to examine.
		// (This also means you can't scan outside the web space. Guess you could
		//  regard this as a "feature.")
//		echo "Skipping link: $entry<br/>\r\n";
		continue;
	}
	else if (is_file($thisEntry)) {
		// It's a file
//		echo "Reading: $entry<br/>\r\n";
		$stat = stat($thisEntry);
		$totalFiles++;

		// Read this properties file and add its contents to the data array
		readMoreProps($thisEntry, $data);
	}
	else {
		echo "oik? $thisEntry<br/>\r\n";
	echo "<span class='RED_ALERT'>oik? $thisEntry surprised me.</span><br/>\r\n";
	}
}

ksort($data);
// print_r($data);

echo "<table cellspacing='4' cellpadding='2' border='0'>\r\n";
foreach ($data as $url => $values) {
	$values['emails'] = str_replace(array('<','>'), array('&lt;','&gt;'), $values['emails']);
	$URL_HASH = md5($url);
	echo "<tr><td width='50%' valign='top' class='TABLE_ROW'>$url&nbsp;<a href='$url' target='_blank' style='text-decoration:none;'>&rarr;</a><br/><span class='LOWER_LINE'>&nbsp;&nbsp;$values[filters]&nbsp;&nbsp;||&nbsp;&nbsp;$values[emails]&nbsp;&nbsp;||&nbsp;&nbsp;".$URL_HASH."</span></td><td width='50%' valign='top' class='TABLE_ROW'>$values[ID]</td></tr>\r\n";
}
echo "</table>\r\n";
?>
</BODY>
</HTML>