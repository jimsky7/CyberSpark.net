<?php
include('cs-log-pw.php');
include('cs-log-config.php');
require ('cs-log-functions.php');

////////////////////////////////////////////////////////////////////////
// Start at http://cyberspark.net/
//   The code is at github. Find the link on the CyberSpark site.
// Using http://D3js.org for visualization
//	 Tutorials at http://bost.ocks.org/mike/bar/3/

////////////////////////////////////////////////////////////////////////
//	Single frame SVG chart
$TITLE			= '';
$URL_HASHES		= array(
					'7debeb1aee721e4f1ebdf504bfeae006'  // peoplepower.hk
			);

////////////////////////////////////////////////////////////////////////
// EVERYTHING AFTER THIS IS COMMON

// Note: This code is usually invoked via POST, with a bunch of input variables.
//   However, it may be invoked via GET with year, month, and day only:
//     https://viz.cyberspark.net/analysis/index.php?YEAR=2014&MONTH=6&DAY=5
//   The parameter names must be uppercase.

////////////////////////////////////////////////////////////////////////
require ('cs-log-functions.php');

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
// Determine whether date is "NOW" or a specific calendar date
$calendar=false;
if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST')==0) {
//	echo '<!-- POST -->';
	if (isset($_POST['SUBMIT_CALENDAR'])) {
//		echo '<!-- SUBMIT_CALENDAR '.$_POST['SUBMIT_CALENDAR'].' -->';
		$calendar = true;
	}
	$direction = 0;
	if (isset($_POST['DIRECTION'])) {
//		echo '<!-- DIRECTION '.$_POST['DIRECTION'].' -->';
		if (strcasecmp($_POST['DIRECTION'], 'minus')==0) {
			$direction = -1;
			$calendar = true;
		}
		if (strcasecmp($_POST['DIRECTION'], 'plus')==0) {
			$direction = +1;
			$calendar = true;
		}
	}
	if (isset($_POST['SUBMIT_NOW'])) {
//		echo '<!-- SUBMIT_NOW -->';
	}
}
if (strcasecmp($_SERVER['REQUEST_METHOD'], 'GET')==0) {
//	echo '<!-- GET -->';
	if (($getYEAR = ifGetOrPost('YEAR')) != null && ($getMONTH = ifGetOrPost('MONTH')) != null && ($getDAY = ifGetOrPost('DAY')) != null) {
//		$_SESSION['YEAR'] = (int)$getYEAR;		// converts string to integer, avoid SQL injections
//		$_SESSION['MONTH']= (int)$getMONTH;		// converts string to integer, avoid SQL injections
//		$_SESSION['DAY']  = (int)$getDAY;		// converts string to integer, avoid SQL injections
		$calendar = true;		
	}
}
if (!$calendar) {
	$_SESSION['MONTH'] = date('m');
	$_SESSION['DAY']   = date('j');
	$_SESSION['YEAR']  = date('Y');
//	echo "<!-- DATE DEFAULTED TO 'now': $_SESSION[MONTH]-$_SESSION[DAY]-$_SESSION[YEAR] $_SERVER[REQUEST_METHOD]-->";
}

////////////////////////////////////////////////////////////////////////
$URLS 			= array();
$getDataURL		= array();
$sites			= array();

////////////////////////////////////////////////////////////////////////
// Set up dates+times based on span or explicit dates given
$startTimestamp = new DateTime();

$startDate = '';
$endDate   = '';
$startTimestamp =  0;
$endTimestamp   =  0;

if ($calendar) {
//	echo '<!-- calendaring -->';
	$s = ifGetOrPost('MONTH');
	if ($s != null) {
		if (strlen($s) < 2) {
			$s = '0'.$s;			// add leading zero
		}
		$_SESSION['MONTH'] = $s;
	}
	else {
		if (!isset($_SESSION['MONTH'])) {
			$_SESSION['MONTH'] = '01';
		}
	}
//	echo "<!-- MONTH:$_SESSION[MONTH] -->";
	if (($s = ifGetOrPost('DAY')) != null) {
		if (strlen($s) < 2) {
			$s = '0'.$s;			// add leading zero
		}
		$_SESSION['DAY'] = $s;
		if ($_SESSION['DAY'] == 0) {
			$_SESSION['DAY'] = '01';
		}
	}
	else {
		if (!isset($_SESSION['DAY'])) {
			$_SESSION['DAY'] = '01';
		}
	}
//	echo "<!-- DAY:$_SESSION[DAY] -->";
	if (($s = ifGetOrPost('YEAR')) != null) {
		$_SESSION['YEAR'] = $s;
	}
	else {
		if (!isset($_SESSION['YEAR'])) {
			$_SESSION['YEAR'] = date('Y');
		}
	}
//	echo "<!-- YEAR:$_SESSION[YEAR] -->";
}

////////////////////////////////////////////////////////////////////////
// Visual start-end dates
if ($calendar) {
	$dt   = new DateTime("$_SESSION[YEAR]-$_SESSION[MONTH]-$_SESSION[DAY] 00:00:00");
	$dtm2 = new DateTime("$_SESSION[YEAR]-$_SESSION[MONTH]-$_SESSION[DAY] 00:00:00");
	$dtm2->add(new DateInterval($span));
// Moving backward or forward in time?
	if ($direction > 0) {
		$dt->add(new DateInterval($span));
		$dtm2->add(new DateInterval($span));
	}
	if ($direction < 0) {
		$dt->sub(new DateInterval($span));
		$dtm2->sub(new DateInterval($span));
	}
	if ($direction) {
		$_SESSION['YEAR'] = $dt->format('Y');
		$_SESSION['MONTH']= $dt->format('m');
		$_SESSION['DAY']  = $dt->format('d');
	}
	$startTimestamp = ((int)$dt->format('U'))*1000;
	$endTimestamp   = ((int)$dtm2->format('U'))*1000;
	$startDate = $dt->format('d-M-Y').' [UTC]';
	$endDate   = $dtm2->format('d-M-Y').' [UTC]';
}
else {
	$dt = new DateTime;
	$endTimestamp = ((int)$dt->format('U'))*1000;
	$endDate = $dt->format('d-M-Y H:i');
	$dt->sub(new DateInterval($span));
	$startTimestamp = ((int)$dt->format('U'))*1000;
	$startDate = $dt->format('d-M-Y H:i');
}
//	echo "End: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";
//	echo "Start: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";

////////////////////////////////////////////////////////////////////////
function cs_http_get($url) {
	$result = '';
	try {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 				$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,		true);
		if(defined('CS_HTTP_USER') && defined('CS_HTTP_PASS')) {
			// You can define user name and password in cs-log-pw.php
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_USERPWD, CS_HTTP_USER.':'.CS_HTTP_PASS);
		}
		$curlResult = curl_exec($ch);
		if ($curlResult === FALSE) {
			$result = "Error ".curl_errno($ch).": ".curl_error($ch);
		}
		else {
			$result = $curlResult;
		}
		curl_close($ch);
	}
	catch (Exception $chgx) {
//		$result = '<!-- Exception: '.$chgx->getMessage()." -->\r\n";
	}
	return $result;
}

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
		$sites[$key]			= cs_http_get($getHashFullURL);
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
// Write arrays for hashes themselves, URLs from hashes, data URLs to fetch data

?>
    <script type="text/javascript">
var hashes = [<?php
	$i = 0;
	$stop = count($URL_HASHES) - 1;
	while ($i < $stop) {
		echo "'".$URL_HASHES[$i++]."',";
	}
	echo "'".$URL_HASHES[$i]."'";
?>];
var urlFromHash = {};
<?php
	$i = 0;
	$stop = count($sites);
	while ($i < $stop) {
		echo  "urlFromHash['".$URL_HASHES[$i]."']='".$sites[$i]."';\r\n";
		$i++;
	}
?>
var getDataURL = [<?php
	$i = 0;
	$stop = count($getDataURL) - 1;
	while ($i < $stop) {
		echo "'".$getDataURL[$i++]."',";
	}
	echo "'".$getDataURL[$i]."'";
?>];
var data;
var CHART_MAX = 6;			/* clip any values larger than this */
var CHART_ALERT = 300;		/* this value of http_ms (or higher) signals an alert */
var CHART_TIMEOUT = 59;		/* this value of http_ms (or higher) signals timeout  */
var CHART_MAGENTA = CHART_TIMEOUT; /* value of http_ms for timeout warning */
var CHART_RED = CHART_MAX;	/* this is the value of http_ms that means 'off the charts' */
var CHART_ORANGE = 4;		/* above this turns orange */
var CHART_YELLOW = 2;		/* above this turne yellow */
var CHART_GREEN = 0;			

<?php
define ('CHART_MAX', 6);		/* clip any values larger than this */
define ('CHART_ALERT', 300);	/* this value of http_ms (or higher) signals an alert */
define ('CHART_TIMEOUT', 59);	/* this value of http_ms (or higher) signals timeout  */
define ('CHART_MAGENTA', CHART_TIMEOUT); /* value of http_ms for timeout warning */
define ('CHART_RED', CHART_MAX);	/* this is the value of http_ms that means 'off the charts' */
define ('CHART_ORANGE', 4);		/* above this turns orange */
define ('CHART_YELLOW', 2);		/* above this turne yellow */
define ('CHART_GREEN',  0);		


////////////////////////////////////////////////////////////////////////
// Begin actual javascript to create the charts
// The real work is done in 'd3.tsv' which reads a file and builds an SVG object
?>
var width  = <?php echo $WIDTH_CHART; ?>; /* default */
var height = <?php echo $HEIGHT_CHART; ?>;

var divTooltip = d3.select("body").append("div")   
    .attr("class", "tooltip")
	.attr("id", "DIV_TT")              
	.style("opacity", 0);

var ix=0;
var chart = [];

for (ix=0; ix<hashes.length; ix++) {
	
	var thisID = "H_" + hashes[ix];
	var thisSVG = document.getElementById(thisID);
	var thisWidth = thisSVG.width.baseVal.value;	

	var y = d3.scale.linear()
	    .range([height, 0]);

	var x = d3.scale.ordinal()
	    .rangeRoundBands([0, thisWidth], .1);
	
	chart[hashes[ix]] = d3.selectAll("#"+thisID)
		.attr("width", thisWidth)
		.attr("height", height);
}
<?php
	// Write d3.tsv() calls to create all charts (SVG objects)
	// Note: reason we write them explicitly is that the 'hashes[$key]' is bound at callback time and
	//	has to be a constant in each case.
	foreach ($sites as $key=>$value) {
		echo "d3.tsv(getDataURL[$key], type, function (error,data) { return csIntensityPlot(error,data,hashes[$key]); });\r\n";
	}
?>
<?php
////////////////////////////////////////////////////////////////////////
// Javascript functions that are used by D3 to build the charts
?>
function csIntensityPlot(error,data,hash) {
//	y.domain([0,     d3.max(data, function(d) { return Math.min(CHART_MAX,d.http_ms); })]);
	y.domain([-0.20, d3.max(data, function(d) { return CHART_MAX; })]);	// height is always CHART_MAX

//	var section = d3.selectAll(".chart");
//	var div = section.append("div");
//	div.html("<?php echo $sites[0]; ?> { "+data.length+" data points }");
//	div.style("color","black");
//	div.style("font-weight","bold");

	var barWidth = width / data.length;
	var barMargin = 0;
	var bar = chart[hash].selectAll("g")
      .data(data)
    .enter().append("g")
      .attr("transform", function(d, i) { return "translate(" + i * barWidth + ",0)"; });

	bar.append("rect")
//      .attr("y", function(d) { return y(d.http_ms); })
//      .attr("height", function(d) { return height - y(d.http_ms); })
      .attr("y", function(d) { return y(Math.min(CHART_MAX,d.http_ms)); })
      .attr("height", function(d) { return height - y(Math.min(CHART_MAX,d.http_ms)); })
      .attr("class", function(d) { 
      		if (d.http_ms>CHART_ALERT) { 
      			return "rect_alert"; 
      		} else { 
      			return (
      		(d.http_ms>CHART_MAGENTA)?"rect_magenta":	
      		((d.http_ms>CHART_RED)?"rect_red":
      		((d.http_ms>CHART_ORANGE)?"rect_orange":
      		((d.http_ms>CHART_YELLOW)?"rect_yellow":
      		"rect_green")))
				); 
      		} 
      	})
	  .attr("width", function(d) { hash = d.URL_HASH; return (barWidth - barMargin); })	/* just a hack to get the hash */
	  .attr("width", barWidth - barMargin)
      .on("mouseover", function(d) {      
            divTooltip.transition()        
                .duration(200)      
                .style("opacity", .80);      
            divTooltip.html(
            	(
					(d.result_code>CHART_ALERT)?
            		('{ HTTP '+d.result_code+' } &raquo; '):
					(
						(d.http_ms>CHART_TIMEOUT)?
            			('{ TIMEOUT '+d.http_ms+'s } &raquo; '):
            				('{'+d.http_ms+'s} ')
            		)
            	)+d.date)  
                .style("left", (((d3.event.pageX+<?php echo $WIDTH_TT; ?>)>window.innerWidth)?(window.innerWidth-<?php echo $WIDTH_TT; ?>):(d3.event.pageX)) + "px")     
                .style("top", (d3.event.pageY - 28) + "px");    
            })                  
      .on("mouseout", function(d) {       
            divTooltip.transition()        
                .duration(500)      
                .style("opacity", 0);   
        });
		
	// Append a chart title containing the HASH or URL
	var urlText = urlFromHash[hash];
	var titleText = urlText.replace('http://','')+" { "+data.length+" samples }";
	// Rectangle behind text
	chart[hash].append("rect")
	    .attr("x", 12)             
	    .attr("y", 2)
 	    .attr("width", (urlText.length*6)+60)  /* use an estimated length */           
	    .attr("height", 17)
		.attr("id", "RT_"+hash)
	    .attr("fill", "white")
	    .attr("fill-opacity", 0.85);
	// Text (URL)
	chart[hash].append("text")
	    .attr("x", 14)             
	    .attr("y", 15)
    	.attr("class", "title")
		.attr("id", "TT_"+hash)
	    .style("font-size", "12px") 
	    .style("font-weight", "bold") 
	    .text(titleText.replace('http://',''));
	// link
	chart[hash].append("a")
     	.attr("target", "_blank")
    	.attr("xlink:href", urlText)
 		.append("rect")  
    	.attr("x", 0)
    	.attr("y", 0)
    	.attr("height", 8)
    	.attr("width",  8)
    	.style("fill", "#e0e0e0")	/* 8020A0 */
    	.attr("rx", 1)
    	.attr("ry", 1);

// >>> Debugging the code below
// >>> Trying to figure width of text and then set the width of the rectangle behind it
// Adjust the width of the rectangle
var textElement = chart[hash].selectAll("#TT_"+hash)[0];
	textElement = textElement[0];
var textRect = textElement.getBBox();
var rectElement = chart[hash].selectAll("#RT_"+hash)[0];
	rectElement = rectElement[0];
//  Until this code is finished, the estimate made above works pretty well
//  The line below does not work
//	rectElement.attr("width", textRect.width);
}

function type(d) {
	/* This function is customized for CyberSpark data */
	d.http_ms = +d.http_ms; // coerce to number
	if (d.result_code > 299) {
		/* If result code indicates HTTP error, plug it in as `http_ms` for later possible action */
		d.http_ms = +d.result_code;
	}
	/* Note: if you return null, this data point will be ignored */
	return d;
}
		
	</script>
<?php
////////////////////////////////////////////////////////////////////////
// Page footer
?>
<!-- legend -->
<div id="CS_FOOTER_NARROW">
<table id='LEGEND_NARROWX' cellspacing='0' cellpadding='0' border='0' style='width:100%; max-width:<? echo CHART_NARROW; ?>px; font-size:11px; margin-top:7px;'>
		<tr>
			<td class='rect_green'   style='height:20px; width:55px; color:White;'>&nbsp;&nbsp;seconds</td>
			<td class='rect_yellow'  style='height:20px; width:40px; color:black;'>&nbsp;&nbsp;<?php echo CHART_YELLOW; ?> +</td>
			<td class='rect_orange'  style='height:20px; width:40px; color:White;'>&nbsp;&nbsp;<?php echo CHART_ORANGE; ?> +</td>
			<td class='rect_red'     style='height:20px; width:40px; color:White;'>&nbsp;&nbsp;<?php echo CHART_RED; ?> +</td>
			<td                      style='height:20px; width:15px;'></td>
			<td class='rect_blue'    style='height:20px; width:45px; color:White;'>&nbsp;Error</td>
			<td                      style='height:20px; width:5px;'></td>
			<td class='rect_magenta' style='height:20px; width:55px; color:White;'>&nbsp;Timeout</td>
		</tr>
	</table>
 </div>
</body>
</html>