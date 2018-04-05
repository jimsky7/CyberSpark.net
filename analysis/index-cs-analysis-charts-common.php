<?php
////////////////////////////////////////////////////////////////////////
// EVERYTHING AFTER THIS IS COMMON

// Note: This code is usually invoked via POST, with a bunch of input variables.
//   However, it may be invoked via GET with year, month, and day only:
//     https://viz.cyberspark.net/analysis/index.php?YEAR=2014&MONTH=6&DAY=5
//   The parameter names must be uppercase.

////////////////////////////////////////////////////////////////////////
include('cs-log-pw.php');
include('cs-log-config.php');
require ('cs-log-functions.php');
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

////////////////////////////////////////////////////////////////////////
// Common setup; check date requested (GET/POST); define variables
include ('index-cs-analysis-setupdates.php');

////////////////////////////////////////////////////////////////////////
// Begin HTML

?>
<html>
	<!-- 2015-08-07 or later -->
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
	<title><?php echo $TITLE; ?></title>
    <link href="css/d3.css"          rel="stylesheet" type="text/css" media="all" /> 
	<link href="css/cs-analysis.css" rel="stylesheet" type="text/css" media="all" />    
    <script type="text/javascript">
		var timer = 0;
		var counter=0;
		var chartsWaiting = true;   /* Means charts not yet rendered */
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
		/* GIF spinner indicates waiting for charts data */
		var hs = setInterval( function() { hideSpinner() } , 500);
		function hideSpinner() {
			if (!chartsWaiting) {
				var spinner = document.getElementById("CS_CHARTS_WRAP");
    			spinner.style.backgroundImage = "";
			}
		}
	</script>
</head>
<body<?php if (!$calendar) { ?> onload="cs_onload()" <?php } ?>>
    <div id="ENCLOSE_HEADER"> 
    <div id="ENCLOSE_HEADER_LEFT"><a href="http://cyberspark.net/"><img src="images/CyberSpark-banner-320x55.png" id="CS_LOGO" alt="CyberSpark web site"/></a>&nbsp;<a href='index.php'><img src="images/cyberspark-arrow-up-32x32.gif" width="32" height="32" alt="Analysis home page"/></a></div><!-- ENCLOSE_HEADER_LEFT -->
    <div id="ENCLOSE_HEADER_RIGHT">
<?php 
	// Remove GET parameters (may not be any)
	$myURI = $_SERVER['REQUEST_URI'];
	$i = strpos($myURI, '?');
	if ($i > 0) {
		$myURI = substr($myURI, 0, $i);
	}
?><form id="CS_FORM" action="<?php echo $myURI; ?>" method="post">
    <select id="MONTH" name="MONTH" class="CS_SELECTOR">
    	<option value="01" <?php if($_SESSION['MONTH']=='01') { echo 'selected'; } ?>>Jan</option>
    	<option value="02" <?php if($_SESSION['MONTH']=='02') { echo 'selected'; } ?>>Feb</option>
    	<option value="03" <?php if($_SESSION['MONTH']=='03') { echo 'selected'; } ?>>Mar</option>
    	<option value="04" <?php if($_SESSION['MONTH']=='04') { echo 'selected'; } ?>>Apr</option>
    	<option value="05" <?php if($_SESSION['MONTH']=='05') { echo 'selected'; } ?>>May</option>
    	<option value="06" <?php if($_SESSION['MONTH']=='06') { echo 'selected'; } ?>>Jun</option>
    	<option value="07" <?php if($_SESSION['MONTH']=='07') { echo 'selected'; } ?>>Jul</option>
    	<option value="08" <?php if($_SESSION['MONTH']=='08') { echo 'selected'; } ?>>Aug</option>
    	<option value="09" <?php if($_SESSION['MONTH']=='09') { echo 'selected'; } ?>>Sep</option>
    	<option value="10" <?php if($_SESSION['MONTH']=='10') { echo 'selected'; } ?>>Oct</option>
    	<option value="11" <?php if($_SESSION['MONTH']=='11') { echo 'selected'; } ?>>Nov</option>
    	<option value="12" <?php if($_SESSION['MONTH']=='12') { echo 'selected'; } ?>>Dec</option>
    </select>
    <input id="DAY" name="DAY" type="text" size="2" <?php if (isset($_SESSION['DAY'])) { echo ' value="'.$_SESSION['DAY'].'"'; } ?>  class="CS_SELECTOR" />
    <select id="YEAR" name="YEAR"  class="CS_SELECTOR">
<?php
$yx = (int)date('Y');
$ys = $yx;
if (isset($_SESSION['YEAR'])) {
	$ys = (int)$_SESSION['YEAR'];
}
while ($yx > 2009) {
    echo "<option value='$yx'";
    if ($yx==$ys) {
    	echo " selected";
    }
    echo ">$yx</option>\r\n";
	$yx--;
}
?>
	</select><input id='DIRECTION' name='DIRECTION' type='hidden' value='none' /><input id='SUBMIT_CALENDAR' name='SUBMIT_CALENDAR' type='submit' value='Go' />&nbsp;<input id='SUBMIT_MINUS' name='SUBMIT_MINUS' type='image' class='CS_TRIANGLE' src='images/cyberspark-triangle-lf-32x32.gif' value='minus' onclick='var e=document.getElementById("DIRECTION"); e.value="minus";' alt='Earlier time period' title='Earlier time period' /><input id='SUBMIT_PLUS' name='SUBMIT_PLUS' type='image' class='CS_TRIANGLE' src='images/cyberspark-triangle-rt-32x32.gif' value='plus' onclick='var e=document.getElementById("DIRECTION"); e.value="plus";' alt='Later time period' title='Later time period' /><div style='display:inline;' id='CS_CONTROL_PANEL_VERTICAL_SEPARATOR'> </div><input id='SUBMIT_NOW'      name='SUBMIT_NOW'      type='submit' value='Now' alt='Real-time charts' title='Real-time charts' />
</form>    
	</div><!-- ENCLOSE_HEADER_RIGHT -->
	</div><!-- ENCLOSE_HEADER -->
    
   	<div id="CS_TITLES">
    <div class="CS_TITLE"><?php echo $TITLE; ?></div>
<?php
	/**** Determine whether a 'bubbles' counterpart exists for this page.
	
		1) Check my filename (PATH) for the word 'charts'
		2) If I am a 'charts' then substitute 'bubbles' for 'charts' and check
			whether a file by that name exists
		3) If the FILE exists, then change my URI to use 'bubbles' rather than 'charts'
		4) Insert a link to the 'bubbles' version of this page
		
	****/
	$path = __FILE__;
	$chartsDIV = '';
	$i = stripos($path, 'charts');	// check my name for 'charts'
	if ($i !== false) {
		$includedFiles = get_included_files();	// Note that our parent must be top level file
		$fn = str_replace('charts', 'bubbles', $includedFiles[0]);
		if (file_exists($fn)) {
			/**** A 'bubbles' version of this page exists ****/
			$chartsURI = $_SERVER['REQUEST_URI'];
			$chartsURI = str_replace('charts', 'bubbles', $chartsURI);
    		$chartsDIV .= "<div class=\"CS_CHARTS_BUBBLES\"><a href=\"$chartsURI\"><img src=\"images/bubbles-charts-icon.png\" /></a></div>\r\n";
		}
	}
?>
<?php if (!$calendar) { ?>
    <div class="CS_SUBTITLE">&nbsp;&nbsp;(Page reloads every few minutes)<?php echo $chartsDIV; ?>
    </div>
    <div class="CS_SUBTITLE_NARROW">&nbsp;&nbsp;(Page will reload)<?php echo $chartsDIV; ?>
    </div>
<?php } else {?>
    <div class="CS_SUBTITLE">&nbsp;&nbsp;(Archived data)<?php echo $chartsDIV; ?>
	</div>
    <div class="CS_SUBTITLE_NARROW">&nbsp;&nbsp;(Archived data)<?php echo $chartsDIV; ?>
	</div>
<?php } ?>
     </div>
    <hr/>
    <div id="CS_START_END" style="width:<?php echo $WIDTH_CHART; ?>px">
    	<div style="float:left;">&darr;&nbsp;&nbsp;<?php echo $startDate; ?></div>
    	<div style="float:right;"><?php echo $endDate; ?>&nbsp;&nbsp;&darr;</div>
    </div>

<?php
////////////////////////////////////////////////////////////////////////
// Write the SVG objects for the charts

	$thisSite = $_SERVER['SERVER_NAME'];
//	echo "<!-- SERVER_NAME: $thisSite -->\r\n";
	$requestURI = $_SERVER['REQUEST_URI'];
	$i = strrpos($requestURI, '/');
	if ($i > 0) {
		$requestURI = substr($requestURI, 0, $i);
	}
	$getHashURL = 'http://'.$_SERVER['SERVER_NAME'].$requestURI.'/'.CS_URL_FROM_HASH;
	echo "<div id='CS_CHARTS_WRAP' style='display:table; background-image:url(\"images/ajax-loader-large-transparent.gif\"); background-repeat:no-repeat; background-position: 0px 0px; ' >\n";	

	foreach ($URL_HASHES as $key=>$URL_HASH) {
		$getHashFullURL = $getHashURL."?URL_HASH=$URL_HASH";
		// http://example.com/analysis/cs-log-get-url-from-hash.php?URL_HASH=383f65f95363c204221bd6b4cc4d6701
		if (defined('DEBUG') && DEBUG) {
			echo "<!-- getHashURL: $getHashFullURL -->\r\n";
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
include ('index-cs-analysis-footer.php');

?>

</body>
</html>