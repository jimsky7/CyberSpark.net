<?php
include('cs-log-pw.php');
include('cs-log-config.php');

////////////////////////////////////////////////////////////////////////
// Start at http://cyberspark.net/
//   The code is at github. Find the link on the CyberSpark site.
// Using http://D3js.org for visualization
//	 Tutorials at http://bost.ocks.org/mike/bar/3/

////////////////////////////////////////////////////////////////////////
//	$span 			= 'P1D';
$CHART_WIDTH    	= 600;
$CHART_HEIGHT    	= 555;								// bucharest 555
$TITLE			= 'Bubbles &mdash;';
$URL_HASHES		= array(
					'fa6b4072eee87dc06f1cebf28d09489c',  // accessnow.org
					'e059bf58e6df39d6cfcfb71695f36a0a',  // amnesty.org
					'898bffdd0bbcca5964c0361375c151d9',  // transparency.org
					'383f65f95363c204221bd6b4cc4d6701',  // rsf.org
					'0c08336164910de58b6204097724d426',  // msf.org
					'cedbab8fd4859687b3e33f3362e78857',  // viz.cyberspark.net
					'e7a7da9d4c3c84a4e9c2077368638f7d',  // filtershekanha.com
					'71e1fcd3679936abd57115a269d8e6b0',  // securityinabox.org
					'cb47d8639f1684740be58fff727410ff',  // optin.stopwatching.us
					'52274b4a478b65212f6c5dce045e9053',  // shaheedoniran.org
					'c5ab7ef4385440fc4b97aa932d5fef7c',  // savetibet.org
					'a6d30fd8c0b96f3ad629aecfe945958d',  // virtualskeptics.com
					'deb3bf5307231fa5e47bdbe1aa8f658c',  // knowthechain.org
					'9d3ff4a865aac42f230c467200495546',  // metta-activism.org
					'3eaf9a25677e8da2c57eec2a2c8a1feb',  // carnegielibrary.org
					'a8454c442d48577835c96241ea6954c3',  // skeptools
					'a88f27ca8bf9348cce493fd4aafef0e8',  // avaaz.org
					'b41be5921a722c2b051ffabc2eab6388',  // humanrights.asia
					'7debeb1aee721e4f1ebdf504bfeae006',  // peoplepower.hk
					'b3cfb7f0fdcc475cd2409643ebc19c15',  // eng.dphk.pk
					'c921effc4cb67d6a22f98afe43df713c',  // dalailamafoundation.org
					'0916ea889a723c2f691b5a12c175fd09'   // humanityunited.org
			);

?>
<?php
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

if (!isset($BUBBLE_CHART_WIDTH)) {
	if (isset($CHART_WIDTH)) {
		$BUBBLE_CHART_WIDTH    	    = $CHART_WIDTH;
	}
	else {
		$BUBBLE_CHART_WIDTH    	    = 555;
	}
}
if (!isset($BUBBLE_CHART_HEIGHT)) {
	if (isset($CHART_HEIGHT)) {
		$BUBBLE_CHART_HEIGHT    	= $CHART_HEIGHT;
	}
	else {
		$BUBBLE_CHART_HEIGHT    	= 555;
	}
}
if (!isset($span)) {
	$span 			= 'P1D';
}
if (!isset($TITLE)) {
	$TITLE			= 'Untitled &mdash;';
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

$startDate 		= '';
$endDate   		= '';
$startTimestamp =  0;
$endTimestamp   =  0;
$MDY 			= '';

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
	$MDY = "MONTH=$_SESSION[MONTH]&DAY=$_SESSION[DAY]&YEAR=$_SESSION[YEAR]&";
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
		var chartHash       =  '';
		function chart_float() {
			var MARGIN = 20;
			var titles      = document.getElementById("CS_TITLES");
			var titlesRect  = titles.getBoundingClientRect();
			var chartFrame  = document.getElementById("CS_CHART_IFRAME");
			var chartRect   = chartFrame.getBoundingClientRect();
			chartFrame.style.left = titlesRect.right - (chartRect.right-chartRect.left) - MARGIN;
		}
		function chart_onload() {
<?php
			$chartHash = ifGetOrPost('CS_CHART_HASH');
			if (($chartHash != null) && (strlen($chartHash) > 0)) {
				echo "var elt=document.getElementById('CS_CHART_IFRAME'); \r\n";	
				echo "elt.src='https://viz.cyberspark.net/analysis/index-cs-analysis-frame.php?$MDY"."URL_HASH=$chartHash'; \r\n";	
				echo "chartHash='$chartHash';\r\n";
			}	
?>
			chart_float();
		}
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
<body<?php if ($calendar) { ?> onload="chart_onload();" <?php } else { ?> onload="cs_onload(); chart_onload();" <?php }?> onresize="chart_onload();" >
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
?><form id='CS_FORM' action='<?php echo $myURI; ?>' method='post' onsubmit='var elt=document.getElementById("CS_CHART_HASH"); elt.value=chartHash;'>
    <select id='MONTH' name='MONTH' class='CS_SELECTOR'>
    	<option value='01' <?php if($_SESSION['MONTH']=='01') { echo 'selected'; } ?>>Jan</option>
    	<option value='02' <?php if($_SESSION['MONTH']=='02') { echo 'selected'; } ?>>Feb</option>
    	<option value='03' <?php if($_SESSION['MONTH']=='03') { echo 'selected'; } ?>>Mar</option>
    	<option value='04' <?php if($_SESSION['MONTH']=='04') { echo 'selected'; } ?>>Apr</option>
    	<option value='05' <?php if($_SESSION['MONTH']=='05') { echo 'selected'; } ?>>May</option>
    	<option value='06' <?php if($_SESSION['MONTH']=='06') { echo 'selected'; } ?>>Jun</option>
    	<option value='07' <?php if($_SESSION['MONTH']=='07') { echo 'selected'; } ?>>Jul</option>
    	<option value='08' <?php if($_SESSION['MONTH']=='08') { echo 'selected'; } ?>>Aug</option>
    	<option value='09' <?php if($_SESSION['MONTH']=='09') { echo 'selected'; } ?>>Sep</option>
    	<option value='10' <?php if($_SESSION['MONTH']=='10') { echo 'selected'; } ?>>Oct</option>
    	<option value='11' <?php if($_SESSION['MONTH']=='11') { echo 'selected'; } ?>>Nov</option>
    	<option value='12' <?php if($_SESSION['MONTH']=='12') { echo 'selected'; } ?>>Dec</option>
    </select>
    <input id='CS_CHART_HASH' name='CS_CHART_HASH' type='hidden' value='' />
    <input id='DAY' name='DAY' type='text' size='2' <?php if (isset($_SESSION['DAY'])) { echo ' value="'.$_SESSION[DAY].'"'; } ?>  class='CS_SELECTOR' />
    <select id='YEAR' name='YEAR'  class='CS_SELECTOR'>
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
    <div class="CS_TITLE"><? echo $TITLE; ?></div>
<?php if (!$calendar) { ?>
    <div class="CS_SUBTITLE">&nbsp;&nbsp;(Page reloads every few minutes)</div>
    <div class="CS_SUBTITLE_NARROW">&nbsp;&nbsp;(Page will reload)</div>
<?php } else {?>
    <div class="CS_SUBTITLE">&nbsp;&nbsp;(Archived data)</div>
    <div class="CS_SUBTITLE_NARROW">&nbsp;&nbsp;(Archived data)</div>
<?php } ?>
    </div>
    <hr/>
    <div id="CS_START_END" style="width:<?php echo $WIDTH_CHART; ?>px">
<?php
/****
    	<div style="float:left;">&darr;&nbsp;&nbsp;<?php echo $startDate; ?></div>
    	<div style="float:right;"><?php echo $endDate; ?>&nbsp;&nbsp;&darr;</div>
****/
?>
    	<div style="float:left;"><?php echo $endDate; ?></div>
    </div>

<?php
////////////////////////////////////////////////////////////////////////
// Write the SVG for the bubbles chart

	$thisSite = $_SERVER['SERVER_NAME'];
//	echo "<!-- SERVER_NAME: $thisSite -->\r\n";
	$requestURI = $_SERVER['REQUEST_URI'];
	$i = strrpos($requestURI, '/');
	if ($i > 0) {
		$requestURI = substr($requestURI, 0, $i);
	}
	$getHashURL = 'http://'.$_SERVER['SERVER_NAME'].$requestURI.'/'.CS_URL_FROM_HASH;
	echo "<div id='CS_CHARTS_WRAP' style='display:table;'>\n";	
?>

<script>
var chartWidth  = <?php echo $BUBBLE_CHART_WIDTH;  ?>;
var chartHeight = <?php echo $BUBBLE_CHART_HEIGHT; ?>;

var diameter = Math.min(chartWidth, chartHeight);
var format = d3.format(",d");
var color = d3.scale.category20c();

var bubble = d3.layout.pack()
    .sort(null)
    .size([chartWidth, chartHeight])
    .padding(1.5);

var svg = d3.select("body").append("svg")
    .attr("width", chartWidth)
    .attr("height", chartHeight)
    .attr("class", "bubble");

<?php
define ('CHART_MAX', 6);		/* clip any values larger than this */
define ('CHART_ALERT', 300);	/* this value of http_ms (or higher) signals an alert */
define ('CHART_TIMEOUT', 59);	/* this value of http_ms (or higher) signals timeout  */
define ('CHART_MAGENTA', CHART_TIMEOUT); /* value of http_ms for timeout warning */
define ('CHART_RED', CHART_MAX);	/* this is the value of http_ms that means 'off the charts' */
define ('CHART_ORANGE', 4);		/* above this turns orange */
define ('CHART_YELLOW', 2);		/* above this turne yellow */
define ('CHART_GREEN',  0);
define ('CHART_MIN', 0.050);		/* minimum where bubble turns white */	
define ('MIN_RADIUS', 15);		/* minimum bubble radius */	
?>
var CHART_MAX     = <?php echo CHART_MAX; ?>;			/* clip any values larger than this */
var CHART_ALERT   = <?php echo CHART_ALERT; ?>;		/* this value of http_ms (or higher) signals an alert */
var CHART_TIMEOUT = <?php echo CHART_TIMEOUT; ?>;		/* this value of http_ms (or higher) signals timeout  */
var CHART_MAGENTA = CHART_TIMEOUT; /* value of http_ms for timeout warning */
var CHART_RED = CHART_MAX;	/* this is the value of http_ms that means 'off the charts' */
var CHART_ORANGE  = <?php echo CHART_ORANGE; ?>;		/* above this turns orange */
var CHART_YELLOW  = <?php echo CHART_YELLOW; ?>;		/* above this turns yellow */
var CHART_GREEN   = <?php echo CHART_GREEN; ?>;
var CHART_MIN     = <?php echo CHART_MIN; ?>;
var MIN_RADIUS     = <?php echo MIN_RADIUS; ?>;

var fill_magenta= 'magenta';
var fill_timeout= fill_magenta;
var fill_red    = 'red';
var fill_orange = 'orange';
var fill_yellow = 'yellow';
var fill_green  = 'green';
var fill_white  = 'white';
var fill_black  = 'black';
var fill_blue   = 'blue';
var fill_alert  = fill_blue;			

<?php
	$s = '?';
	foreach ($URL_HASHES as $hash) {
		$s .= $hash.'&';
	}
	$s = trim($s, '&');
	if ($calendar) {
		$s .= "&MONTH=$_SESSION[MONTH]&DAY=$_SESSION[DAY]&YEAR=$_SESSION[YEAR]";
	}
?>
d3.json("cs-log-get-bubbles.json<?php echo $s; ?>", function(error, tree) {
<?php  /* Each node (bubble) */ ?>
  var node = svg.selectAll(".node")
      .data(bubble.nodes(crawl(tree))
      .filter(function(d) { return !d.children; }))
    .enter().append("g")
      .attr("class", "node")
      .attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; });

<?php  /* Tooltip */ ?>
  node.append("title")
		.text(function(d) { 
	    	dv = Math.trunc(d.http_ms*1000)/1000;
	  		var df = d3.format('g');
			var cn = d.nodeName;
			cn = cn.replace('http://', ''); 
			cn = cn.replace('https://', ''); 
			cn = cn.replace('www.', ''); 
			if (d.http_ms>CHART_ALERT) {
				return (cn + " » HTTP " + d.http_ms); 
			}
			else {
				return (cn + " » " + df(dv) + " s"); 
			}
		});

<?php  /* The solid circle */ ?>
  node.append("circle")
		.attr("r", function(d) { return (Math.max(MIN_RADIUS,d.r)); })
		.style("stroke", function(d) { 
			return (
				(d.http_ms>CHART_ALERT)?fill_alert:
				(d.http_ms>CHART_TIMEOUT)?fill_timeout:
				(d.http_ms>CHART_MAGENTA)?fill_magenta:	
				(d.http_ms>CHART_RED)?fill_red:
				(d.http_ms>CHART_ORANGE)?fill_orange:
				(d.http_ms>CHART_YELLOW)?fill_yellow:
				(d.http_ms<=CHART_MIN)?fill_black:
				fill_green
			);
		})
		.style("fill", function(d) { 
			return (
				(d.http_ms>CHART_ALERT)?fill_alert:
				(d.http_ms>CHART_TIMEOUT)?fill_timeout:
				(d.http_ms>CHART_MAGENTA)?fill_magenta:	
				(d.http_ms>CHART_RED)?fill_red:
				(d.http_ms>CHART_ORANGE)?fill_orange:
				(d.http_ms>CHART_YELLOW)?fill_yellow:
				(d.http_ms<=CHART_MIN)?fill_white:
				fill_green
			);
		});

  node.append("a")
     	.attr("target", "CS_CHART_IFRAME")
    	.attr("xlink:href", function(d) { return ("https://viz.cyberspark.net/analysis/index-cs-analysis-frame.php?<?php echo $MDY; ?>URL_HASH="+d.URL_HASH); })
    	.on("click", function(d) { chartHash=d.URL_HASH; return true; })
		.append("circle")
    	.attr("r",   3)
    	.style("stroke", function(d) { return fill_white; })
    	.attr("cx",  0)
    	.attr("cy", 15)
    	.style("fill", function(d) { return ((d.http_ms<=CHART_MIN)?"#202020":"#e0e0e0"); } )	/* 8020A0 */
		.style("opacity", 0.9);
<?php  /* Text in the center of the circle */ ?>
  node.append("text")
      .attr("dy", ".3em")
      .style("text-anchor", "middle")
      .style("font-size", "12px")
      .text(function(d) { 
	  		/* Add a possible second line */
			node.append("text")
      			.attr("dy", "1.6em")
      			.style("text-anchor", "middle")
      			.text(function(d) { return ( (d.http_ms > CHART_ALERT)?("[" + d.http_ms + "]"):((d.http_ms > CHART_TIMEOUT)?("[TIMEOUT]"):"")); })
      			.attr("fill", function(d) { 
					return (
						(d.http_ms>CHART_ALERT)?fill_white:
						(d.http_ms>CHART_TIMEOUT)?fill_white:
						(d.http_ms>CHART_MAGENTA)?fill_white:	
						(d.http_ms>CHART_RED)?fill_white:
						(d.http_ms>CHART_ORANGE)?fill_white:
						(d.http_ms>CHART_YELLOW)?fill_black:
						(d.http_ms<=CHART_MIN)?fill_black:
						fill_white
					); 
				});
			/* Main text() */
			var cn = d.nodeName;
			cn = cn.replace('http://', ''); 
			cn = cn.replace('https://', ''); 
			cn = cn.replace('www.', ''); 
			return (cn.substring(0, (d.r/4))); })
      .attr("fill", function(d) { 
			return (
				(d.http_ms>CHART_ALERT)?fill_white:
				(d.http_ms>CHART_TIMEOUT)?fill_white:
				(d.http_ms>CHART_MAGENTA)?fill_white:	
				(d.http_ms>CHART_RED)?fill_white:
				(d.http_ms>CHART_ORANGE)?fill_white:
				(d.http_ms>CHART_YELLOW)?fill_black:
				(d.http_ms<=CHART_MIN)?fill_black:
				fill_white
			); 
		});
		
});

// Returns a flattened hierarchy containing all leaf nodes under the root.
function crawl(tree) {
  var nodes = [];

  function recurse(name, node) {
    if (node.children) node.children.forEach(function(child) { recurse(node.name, child); });
    else nodes.push({ packageName: name, 
		nodeName: node.name, 
		value: ((node.size>CHART_ALERT)?CHART_MAX:((node.size>CHART_TIMEOUT)?CHART_MAX:((node.size<CHART_MIN)?CHART_MIN:node.size))), 
		/* Cyberspark names */
		http_ms: node.size,
		url: node.name,
		URL_HASH: node.URL_HASH,
		chartAlert: ((node.size>CHART_ALERT)?node.size:0)
		});
  }

  recurse(null, tree);
  return {children: nodes};
}

d3.select(self.frameElement).style("height", diameter + "px");

var data;

</script>
<?php
//	echo "</div>\n";		// #CS_CHARTS_WRAP (?)

////////////////////////////////////////////////////////////////////////
// Write arrays for hashes themselves, URLs from hashes, data URLs to fetch data

?>
<iframe src="index-cs-analysis-bubbles-iframe.php" id="CS_CHART_IFRAME" name="CS_CHART_IFRAME" width="320px" height="110px" style="z-index:3; position:absolute; left:440; border:thin; border-style:solid; border-color:#d0d0d0; background-color:none; opacity:0.80;"  >
</iframe>

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
</script>

<?php
echo "</div>\n";		// #CS_CHARTS_WRAP (?)

////////////////////////////////////////////////////////////////////////
// Page footer
?>
<!-- legend -->
<hr/>
<div id="CS_FOOTER_NARROW">
<table id='LEGEND_NARROW' cellspacing='0' cellpadding='0' border='0' style='width:100%; max-width:<? echo CHART_NARROW; ?>px; font-size:11px;'>
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
         <tr>
        	<td colspan='12' style='padding:8px; border:thin; border-style:solid; border-width:1px; border-color:#d0d0d0;'>Mouseover (or tap) the colored bars in a chart to see details.<br/>
        	Click (or tap) a chart to view the associated URL.<br/>
        	Resize or maximize and the charts will float to fill the window.
        	</td>
        </tr>
        <tr>
        	<td colspan='12'><a href="http://creativecommons.org/licenses/by-nc-sa/3.0/" target="_blank"><img src="images/CC-by-nc-sa-88x31.png" width="88" height="31" alt="Creative Commons license" style="margin-top:10px;" /></a>
        	</td>
        </tr>
	</table>
    </div>
    <div id="CS_FOOTER_WIDE" style="float:left; width:100%;">
    <table id='LEGEND_WIDE' cellspacing='0' cellpadding='0' border='0' style='width:100%;max-width:<? echo CHART_WIDE; ?>px; font-size:11px; margin-bottom:4px;'>
		<tr>
			<td colspan='8' style='height:1px;'>&nbsp;
			</td>
		</tr>
		<tr>
			<td class='rect_green'   style='height:20px; width:60px;'></td>
			<td class='rect_yellow'  style='height:20px; width:60px;'></td>
			<td class='rect_orange'  style='height:20px; width:60px;'></td>
			<td class='rect_red'     style='height:20px; width:60px;'></td>
			<td                      style='height:20px; width:5px;'></td>
			<td class='rect_blue'    style='height:20px; padding-left:5px;'></td>
			<td                      style='height:20px; width:5px;'></td>
			<td class='rect_magenta' style='height:20px;'></td>
		</tr>
		<tr>
			<td style='height:4px; '>&nbsp;seconds</td>
 			<td style='height:4px; border-left:thin; border-left-color:grey; border-left-style:solid;'>&nbsp;
 			</td>
			<td style='height:4px; border-left:thin; border-left-color:grey; border-left-style:solid;'>&nbsp;
			</td>
			<td style='height:4px; border-left:thin; border-left-color:grey; border-left-style:solid;'>&nbsp;
			</td>
			<td>
			</td>
			<td style=''>&nbsp;&nbsp;HTTP error
			</td>
			<td>
			</td>
			<td style=''>&nbsp;&nbsp;Timeout
			</td>
		</tr>
		<tr>
			<td colspan='8' style=''>
				<table cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td style='height:20px; width:58px; font-size:10px;'>0</td>
						<td style='height:20px; width:60px; font-size:10px;'><?php echo CHART_YELLOW; ?></td>
						<td style='height:20px; width:60px; font-size:10px;'><?php echo CHART_ORANGE; ?></td>
                        <td style='height:20px; width:60px; font-size:10px;'><?php echo CHART_RED; ?></td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td colspan='8' style='height:4px;'>&nbsp;
			</td>
		</tr>
        <tr>
        	<td colspan='12' style='padding:8px; border:thin; border-style:solid; border-width:1px; border-color:#d0d0d0;'>Mouseover (or tap) the colored bars in a chart to see details.<br/>
        	Click (or tap) a chart to view the associated URL.<br/>
        	Resize or maximize and the charts will float to fill the window.
        	</td>
        </tr>
       <tr>
        	<td colspan='12'>
            <table cellspacing='0' cellpadding='5px' border='0'>
            <tr><td>
            <a href="http://creativecommons.org/licenses/by-nc-sa/3.0/" target="_blank"><img src="images/CC-by-nc-sa-88x31.png" width="88" height="31" alt="Creative Commons license" style="margin-top:10px;" /></a></td>
            <td style='font-size:12px; padding-top:12px;'>CyberSpark open source code is provided under a <a href="http://creativecommons.org/licenses/by-nc-sa/3.0/" target="_blank">Creative Commons by-nc-sa 3.0</a> license
            </td>
            </tr>
            </table>
        	</td>
        </tr>
	</table>
</div>
</body>
</html>