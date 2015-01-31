<?php
////////////////////////////////////////////////////////////////////////
// EVERYTHING AFTER THIS IS COMMON

// Note: This code is usually invoked via POST, with a bunch of input variables.
//   However, it may be invoked via GET with year, month, and day only:
//     https://viz.cyberspark.net/analysis/index.php?YEAR=2014&MONTH=6&DAY=5
//   The parameter names must be uppercase.

////////////////////////////////////////////////////////////////////////
require('cs-log-pw.php');
require('cs-log-config.php');
require('cs-log-functions.php');

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
if (!isset($BUBBLE_TOUCH_DIAMETER)) {
	$BUBBLE_TOUCH_DIAMETER 	  		=  15;
}
if (!isset($span)) {
	$span 			= 'P1D';
}
if (!isset($TITLE)) {
	$TITLE			= 'Untitled &mdash;';
}

////////////////////////////////////////////////////////////////////////
// Common setup; check date requested (GET/POST); define variables
include ('index-cs-analysis-setupdates.php');

////////////////////////////////////////////////////////////////////////
// Begin HTML

?>
<html>
	<!-- 2014-11-10a -->
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
			if ((titlesRect.right - titlesRect.left) > 320) {
				chartFrame.style.left = titlesRect.right - (chartRect.right-chartRect.left) - MARGIN;
			}
			else {
				chartFrame.style.left = 0;
			}
		}
		function chart_onload() {
<?php
			$chartHash = ifGetOrPost('CS_CHART_HASH');
			if (($chartHash != null) && (strlen($chartHash) > 0)) {
				echo "var elt=document.getElementById(\"CS_CHART_IFRAME\"); \r\n";	
				echo "elt.src=\"index-cs-analysis-frame.php?$MDY"."URL_HASH=$chartHash\"; \r\n";	
				echo "chartHash=\"$chartHash\";\r\n";
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
			var URI = "<?php echo $_SERVER['REQUEST_URI']; ?>";
			var i   = URI.indexOf("?");
			if (i >= 0) {
				URI = URI.substring(0,i);
			}
			if (chartHash !== '') {
				window.location.href = URI+"?CS_CHART_HASH="+chartHash;
			}
			else {
				window.location.href = URI;
			}
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
?><form id="CS_FORM" action="<?php echo $myURI; ?>" method="post" onsubmit="var elt=document.getElementById('CS_CHART_HASH'); elt.value=chartHash;">
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
    <input id="CS_CHART_HASH" name="CS_CHART_HASH" type="hidden" value="" />
    <input id="DAY" name="DAY" type="text" size="2" <?php if (isset($_SESSION['DAY'])) { echo ' value="'.$_SESSION[DAY].'"'; } ?>  class='CS_SELECTOR' />
    <select id="YEAR" name="YEAR"  class="CS_SELECTOR">
<?php
$yx = (int)date('Y');
$ys = $yx;
if (isset($_SESSION['YEAR'])) {
	$ys = (int)$_SESSION['YEAR'];
}
while ($yx > 2009) {
    echo "<option value=\"$yx\"";
    if ($yx==$ys) {
    	echo " selected";
    }
    echo ">$yx</option>\r\n";
	$yx--;
}
?>
	</select><input id="DIRECTION" name="DIRECTION" type="hidden" value="none" /><input id="SUBMIT_CALENDAR" name="SUBMIT_CALENDAR" type="submit" value="Go" />&nbsp;<input id="SUBMIT_MINUS" name="SUBMIT_MINUS" type="image" class="CS_TRIANGLE" src="images/cyberspark-triangle-lf-32x32.gif" value="minus" onclick="var e=document.getElementById('DIRECTION'); e.value='minus';" alt="Earlier time period" title="Earlier time period" /><input id="SUBMIT_PLUS" name="SUBMIT_PLUS" type="image" class="CS_TRIANGLE" src="images/cyberspark-triangle-rt-32x32.gif" value="plus" onclick="var e=document.getElementById('DIRECTION'); e.value='plus';" alt="Later time period" title="Later time period" /><div style="display:inline;" id="CS_CONTROL_PANEL_VERTICAL_SEPARATOR"> </div><input id="SUBMIT_NOW"      name="SUBMIT_NOW"      type="submit" value="Now" alt="Real-time charts" title="Real-time charts" />
</form>    
	</div><!-- ENCLOSE_HEADER_RIGHT -->
	</div><!-- ENCLOSE_HEADER -->
    
   	<div id="CS_TITLES">
    <div class="CS_TITLE"><? echo $TITLE; ?></div>
<?php
	/**** Determine whether a 'charts' counterpart exists for this page.
	
		1) Check my filename (PATH) for the word 'bubbles'
		2) If I am a 'bubbles' then substitute 'charts' for 'bubbles' and check
			whether a file by that name exists
		3) If the FILE exists, then change my URI to use 'charts' rather than 'bubbles'
		4) Insert a link to the 'charts' version of this page
		
	****/
	$path = __FILE__;
	$chartsDIV = '';
	$i = stripos($path, 'bubbles');	// check my name for 'bubbles'
	if ($i !== false) {
		$includedFiles = get_included_files();	// Note that our parent must be top level file
		$fn = str_replace('bubbles', 'charts', $includedFiles[0]);
		if (file_exists($fn)) {
			/**** A 'charts' version of this page exists ****/
			$chartsURI = $_SERVER['REQUEST_URI'];
			$chartsURI = str_replace('bubbles', 'charts', $chartsURI);
    		$chartsDIV .= "<div class=\"CS_CHARTS_BUBBLES\"><a href=\"$chartsURI\"><img src=\"images/charts-bubbles-icon.png\" /></a></div>\r\n";
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
define ('CHART_TIMEOUT', 30);	/* this value of http_ms (or higher) signals timeout  */
define ('CHART_CURL', 31);	    /* this value of http_ms signals cURL error */
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
var CHART_CURL    = <?php echo CHART_CURL; ?>;     /* this value indicates a cURL "server failure" error */
var CHART_MAGENTA = CHART_TIMEOUT; /* value of http_ms for timeout warning */
var CHART_RED = CHART_MAX;	/* this is the value of http_ms that means 'off the charts' */
var CHART_ORANGE  = <?php echo CHART_ORANGE; ?>;		/* above this turns orange */
var CHART_YELLOW  = <?php echo CHART_YELLOW; ?>;		/* above this turns yellow */
var CHART_GREEN   = <?php echo CHART_GREEN; ?>;
var CHART_MIN     = <?php echo CHART_MIN; ?>;
var MIN_RADIUS    = <?php echo MIN_RADIUS; ?>;
var CURLE_OPERATION_TIMEDOUT = <?php echo CURLE_OPERATION_TIMEDOUT; ?>;
var CURLE_RECV_ERROR         = <?php echo CURLE_RECV_ERROR;         ?>;		

var fill_magenta= "magenta";
var fill_timeout= fill_magenta;
var fill_red    = "red";
var fill_orange = "orange";
var fill_yellow = "yellow";
var fill_green  = "green";
var fill_white  = "white";
var fill_black  = "black";
var fill_blue   = "blue";
var fill_cyan   = "cyan";
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
<?php  /* For each node (make a bubble) */ ?>
  var node = svg.selectAll(".node")
      .data(bubble.nodes(crawl(tree))
      .filter(function(d) { return !d.children; }))
    .enter().append("g")
      .attr("class", "node")
      .attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; });

<?php  /* Tooltip */ ?>
  node.append("title")
		.text(function(d) { 
	    	dv = Math.floor(d.http_ms*1000)/1000;
	  		var df = d3.format('g');
			var cn = d.nodeName;
			cn = cn.replace("http://",   ""); 
			cn = cn.replace("https://",  ""); 
			cn = cn.replace("www.",      ""); 
			cn = cn.replace(/\/$/, "");
			return (

<?php if (defined('CHART_CURL')) {               ?>					(d.http_ms==CHART_CURL)?(cn + " » CONNECT FAILED or REFUSED \n(" + d.date + ")"):
<?php } ?>
				(d.http_ms>CHART_ALERT                  )?(cn + " » HTTP " + d.http_ms + "\n(" + d.date + ")"):
<?php if (defined('CURLE_OPERATION_TIMEDOUT')) { ?>				(d.result_code==CURLE_OPERATION_TIMEDOUT)?(cn + " » TIMEOUT \n(" + d.date + ")"):
<?php } ?>
<?php if (defined('CURLE_RECV_ERROR')) {         ?>				(d.result_code==CURLE_RECV_ERROR        )?(cn + " » RCV ERROR \n(" + d.date + ")"):
<?php } ?>
				(cn + " » " + df(dv) + " s \n(" + d.date + ")")
			); 
		});

<?php  /* The large solid bubble */ ?>
  node.append("circle")
		.attr("r", function(d) { return (Math.max(MIN_RADIUS,d.r)); })
		.style("stroke", function(d) { 
			return (
				(d.result_code==CURLE_OPERATION_TIMEDOUT)?fill_timeout:
				(d.result_code==CURLE_RECV_ERROR        )?fill_timeout:
				(d.http_ms>CHART_ALERT     )?fill_alert:
				(d.http_ms>CHART_TIMEOUT   )?fill_timeout:
				(d.http_ms>CHART_MAGENTA   )?fill_magenta:	
				(d.http_ms>CHART_RED       )?fill_red:
				(d.http_ms>CHART_ORANGE    )?fill_orange:
				(d.http_ms>CHART_YELLOW    )?fill_yellow:
				(d.http_ms<=CHART_MIN      )?fill_black:
				fill_green
			);
		})
		.style("fill", function(d) { 
			return (
				(d.result_code==CURLE_OPERATION_TIMEDOUT)?fill_timeout:
				(d.result_code==CURLE_RECV_ERROR        )?fill_timeout:
				(d.http_ms==CHART_CURL     )?fill_cyan:
				(d.http_ms>CHART_ALERT     )?fill_alert:
				(d.http_ms>CHART_TIMEOUT   )?fill_timeout:
				(d.http_ms>CHART_MAGENTA   )?fill_magenta:	
				(d.http_ms>CHART_RED       )?fill_red:
				(d.http_ms>CHART_ORANGE    )?fill_orange:
				(d.http_ms>CHART_YELLOW    )?fill_yellow:
				(d.http_ms<=CHART_MIN      )?fill_white:
				fill_green
			);
		});

<?php  /* Text in the center of the circle */ ?>
  node.append("text")
      .attr("dy",            ".3em")
      .style("text-anchor",  "middle")
      .style("font-size",    "12px")
      .text(function(d) { 
	  		/* Add a possible second line */
			node.append("text")
      			.attr("dy",            "2.6em")
      			.style("text-anchor",  "middle")
      			.style("font-size",    "12px")
     			.text(function(d) { 
					return ( 
						(d.http_ms > CHART_ALERT  )?("[ " + d.http_ms + " ]"):
						(d.http_ms==CHART_CURL    )?("[FAILED]"):
						(d.http_ms > CHART_TIMEOUT)?("[TIMEOUT]"):
						""
					); 
				})
      			.attr("fill", function(d) { 
					return (
						(d.result_code==CURLE_OPERATION_TIMEDOUT)?fill_white:
						(d.result_code==CURLE_RECV_ERROR        )?fill_white:
						(d.http_ms==CHART_CURL     )?fill_black:
						(d.http_ms>CHART_ALERT     )?fill_white:
						(d.http_ms>CHART_TIMEOUT   )?fill_white:
						(d.http_ms>CHART_MAGENTA   )?fill_white:	
						(d.http_ms>CHART_RED       )?fill_white:
						(d.http_ms>CHART_ORANGE    )?fill_white:
						(d.http_ms>CHART_YELLOW    )?fill_black:
						(d.http_ms<=CHART_MIN      )?fill_black:
						fill_white
					); 
				});
			/* Main text() */
			var cn = d.nodeName;
			cn = cn.replace("http://",   ""); 
			cn = cn.replace("https://",  ""); 
			cn = cn.replace("www.",      ""); 
			cn = cn.replace(/\/$/, "");
			return (cn.substring(0, (d.r/4))); })
      .attr("fill", function(d) { 
			return (
				(d.result_code==CURLE_OPERATION_TIMEDOUT)?fill_white:
				(d.result_code==CURLE_RECV_ERROR        )?fill_white:
				(d.http_ms==CHART_CURL     )?fill_black:
				(d.http_ms>CHART_ALERT     )?fill_white:
				(d.http_ms>CHART_TIMEOUT   )?fill_white:
				(d.http_ms>CHART_MAGENTA   )?fill_white:	
				(d.http_ms>CHART_RED       )?fill_white:
				(d.http_ms>CHART_ORANGE    )?fill_white:
				(d.http_ms>CHART_YELLOW    )?fill_black:
				(d.http_ms<=CHART_MIN      )?fill_black:
				fill_white
			); 
		});

<?php  /* Small "touchpoint" circle in the center of each bubble */ ?>
  node.append("a")
     	.attr("target", "CS_CHART_IFRAME")
    	.attr("xlink:href", function(d) { 
			return ("index-cs-analysis-frame.php?<?php echo $MDY; ?>URL_HASH="+d.URL_HASH); 
		})
    	.on("click", function(d) { chartHash=d.URL_HASH; return true; })
		.append("circle")
    	.attr("r",         3)
    	.style("stroke",   function(d) { return fill_white; })
    	.attr("cx",        0)
    	.attr("cy",       <?php echo $BUBBLE_TOUCH_DIAMETER; ?>)
    	.style("fill",    function(d) { 
			return (
				(d.result_code==CURLE_OPERATION_TIMEDOUT)?"#e0e0e0":
				(d.result_code==CURLE_RECV_ERROR        )?"#e0e0e0":
				(d.http_ms<=CHART_MIN)?"#202020":
				"#e0e0e0"
			); 
		})
		.style("opacity", 0.9);
		
});

// Returns a flattened hierarchy containing all leaf nodes under the root.
function crawl(tree) {
  var nodes = [];

  function recurse(name, node) {
    if (node.children) node.children.forEach(function(child) { recurse(node.name, child); });
    else nodes.push({ packageName: name, 
		nodeName: node.name, 
		value: (
			(node.result_code==CURLE_OPERATION_TIMEDOUT)?CHART_MAX:
			(node.result_code==CURLE_RECV_ERROR        )?CHART_MAX:
			(node.result_code<100)?CHART_MAX:
			(node.size>CHART_ALERT  )?CHART_MAX:
			(node.size>CHART_TIMEOUT)?CHART_MAX:
			(node.size<CHART_MIN    )?CHART_MIN:
			node.size
			), 
		/* Cyberspark names */
		http_ms: (
			(node.result_code<100)?CHART_CURL:
			node.size
			),
		date: node.date,
		url: node.name,
		URL_HASH: node.URL_HASH,
		chartAlert: ((node.size>CHART_ALERT)?node.size:0),
		result_code: node.result_code
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

</div> <?php // #CS_CHARTS_WRAP 
?>
<?php
////////////////////////////////////////////////////////////////////////
include ('index-cs-analysis-footer.php');
 ?>
 
</body>
</html>