<?php

////////////////////////////////////////////////////////////////////////
// Start at http://cyberspark.net/
//   The code is at github. Find the link on the CyberSpark site.
// Using http://D3js.org for visualization
//	 Tutorials at http://bost.ocks.org/mike/bar/3/

////////////////////////////////////////////////////////////////////////
//	Single frame SVG chart
$TITLE			= '';
$URL_HASHES		= array(
					'b3eaf4bce2b85708a6c930a5d527340d'  // cyberspark.net
			);

require('cs-log-pw.php');
require('cs-log-config.php');
require('cs-log-functions.php');
require ('cs-analysis-functions.php');

////////////////////////////////////////////////////////////////////////
// Check/set any missing layout values
// In general these should be set by the surrounding index.php file and 
// not set down in this common code. Then the index.php should include()
// this file to do all the work of setting up and emitting the page.

if (!isset($WIDTH_TT)) {
	$WIDTH_TT    	= TOOL_TIP_WIDTH;
}
if (!isset($HEIGHT_TT)) {
	$HEIGHT_TT		= TOOL_TIP_HEIGHT;
}
if (!isset($WIDTH_CHART)) {
	$WIDTH_CHART	= CHART_NARROW;
}
if (!isset($HEIGHT_CHART)) {
	$HEIGHT_CHART	= CHART_HEIGHT;
}
if (!isset($span)) {
	$span 			= 'P1D';
}
if (!isset($TITLE)) {
	$TITLE			= 'Untitled &mdash;';
}
if (!isset($CLASS_STYLE)) {
	$CLASS_STYLE   = 'CS_CHART_NARROW';		// CS_CHART_NARROW or CS_CHART_WIDE
}

if (isset($_GET['URL_HASH'])) {
	$URL_HASHES = array($_GET['URL_HASH']);
}
if (isset($_GET['hash'])) {
	$URL_HASHES = array($_GET['hash']);
}


////////////////////////////////////////////////////////////////////////
// Common setup; check date requested (GET/POST); define variables
include ('index-cs-analysis-setupdates.php');

////////////////////////////////////////////////////////////////////////
// Begin HTML

?>
<html>
<head>
	<!-- D3js version 3.4.8 is being used -->
	<script src="/d3/d3.min.js" charset="utf-8"></script>
    <meta charset='utf-8' />
	<meta name="viewport" content="width=device-width; initial-scale=1.0; minimum-scale=1.0; user-scalable=yes;">
<?php
	if (!$calendar) {
?>
   	<!-- refresh page every 60 minutes even if JS fails -->
	<meta http-equiv="refresh" content="3600; url=<?php echo $_SERVER['REQUEST_URI']; ?>">
<?php
	} /* not calendar */
?>
	<title><? echo $TITLE; ?></title>
    <link href="css/d3.css"          rel="stylesheet" type="text/css" media="all" /> 
	<link href="css/cs-analysis.css" rel="stylesheet" type="text/css" media="all" />    
    <script type="text/javascript">
		var timer = 0;
		var counter=0;
		var timeWhenVisible =  <?php echo TIME_WHEN_VISIBLE; ?>;	/* a minute is 60000 */
		var timeWhenHidden  =  <?php echo TIME_WHEN_HIDDEN; ?>;	/* a minute is 60000 */
		function cs_onload() {
			timer = setInterval(cs_reload, (document.hidden) ? timeWhenHidden : timeWhenVisible);
			if(document.addEventListener) document.addEventListener("visibilitychange", visibilityChanged);
		}
		function visibilityChanged() {
			clearTimeout(timer);
			timer = setInterval(cs_reload, (document.hidden) ? timeWhenHidden : timeWhenVisible);
		}
		function cs_reload() { 
			window.location.href = "<?php echo $_SERVER['REQUEST_URI']; ?>";
		}
	</script>
</head>
<body<?php if (!$calendar) { ?> onload="cs_onload()" <?php } ?>>
<?php 
	// Remove GET parameters (may not be any)
	$myURI = $_SERVER['REQUEST_URI'];
	$i = strpos($myURI, '?');
	if ($i > 0) {
		$myURI = substr($myURI, 0, $i);
	}
?>    
    <div id="CS_START_END" style="width:<?php echo $WIDTH_CHART; ?>px">
    	<div style="float:left;">&darr;&nbsp;&nbsp;<?php echo $startDate; ?></div>
    	<div style="float:right;"><?php echo $endDate; ?>&nbsp;&nbsp;&darr;</div>
    </div>

<?php
////////////////////////////////////////////////////////////////////////
// Write the SVG object for one chart [2014-10-29]

	$thisSite = $_SERVER['SERVER_NAME'];
//	echo "<!-- SERVER_NAME: $thisSite -->\r\n";
	$requestURI = $_SERVER['REQUEST_URI'];
	$i = strrpos($requestURI, '/');
	if ($i > 0) {
		$requestURI = substr($requestURI, 0, $i);
	}
	$getHashURL = 'http://'.$_SERVER['SERVER_NAME'].$requestURI.'/'.CS_URL_FROM_HASH;
	echo "<div id='CS_CHARTS_WRAP' style='display:table;'>\n";	
	foreach ($URL_HASHES as $key=>$URL_HASH) {
		$getHashFullURL = $getHashURL."?URL_HASH=$URL_HASH";
		// http://example.com/analysis/cs-log-get-url-from-hash.php?URL_HASH=383f65f95363c204221bd6b4cc4d6701
		if (defined('DEBUG') && DEBUG) {
			echo "<!-- getHashURL: ".$getHashFullURL." -->\r\n";
		}
		try {
			$sites[$key]			= cs_http_get($getHashFullURL);
		}
		catch (Exception $kst) {
			echo "<!-- this failed: $getHashFullURL -->\r\n";
		}
		if (defined('DEBUG') && DEBUG) {
			echo "<!-- sites[$key]: ".$sites[$key]."	 -->\r\n";
		}
		if ($calendar) {
			$getDataURL[$key]	= CS_URL_GET."?format=tsv&URL_HASH=$URL_HASH&pad=true&span=$span&MONTH=$_SESSION[MONTH]&DAY=$_SESSION[DAY]&YEAR=$_SESSION[YEAR]";
		}
		else {
			$getDataURL[$key]	= CS_URL_GET."?format=tsv&URL_HASH=$URL_HASH&pad=true&span=$span";
		}
//		echo "<a href='$sites[$key]' style='text-decoration:none; font-size:12pt;' target='W_$URL_HASH'><svg class='chart ".(isset($CLASS_STYLE)?$CLASS_STYLE:'CS_CHART')."' id='H_$URL_HASH'></svg></a>\r\n";
//		echo "<div style='display:inline; float:left;'><svg class='chart ".(isset($CLASS_STYLE)?$CLASS_STYLE:'CS_CHART')."' id='H_$URL_HASH'></svg><div style='position: relative; left: 2px; top: -38px; height: 10px; width: 10px;'><a href='$sites[$key]' class='CS_SITE_LINK' target='W_$URL_HASH'>»</a></div></div>\r\n";
		echo "<div style='display:inline; float:left;'><svg class='chart ".(isset($CLASS_STYLE)?$CLASS_STYLE:'CS_CHART')."' id='H_$URL_HASH'></svg></div>\r\n";
	}
	echo "</div>\n";		// #CS_TABLE_WRAP

////////////////////////////////////////////////////////////////////////
// Write one SVG chart object
include('index-cs-analysis-SVG.php');

////////////////////////////////////////////////////////////////////////
// Page footer
// Some things will be skipped, but narrow legend is necessary
$skipTips = true;
$skipHR   = true;
$skipCC   = true;
include ('index-cs-analysis-footer.php');

?>
</body>
</html>