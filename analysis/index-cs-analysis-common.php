<?php
////////////////////////////////////////////////////////////////////////
// EVERYTHING AFTER THIS IS COMMON

////////////////////////////////////////////////////////////////////////
// Check/set any missing layout values
if (!isset($WIDTH_TT)) {
	$WIDTH_TT    	= TOOL_TIP_WIDTH;
}
if (!isset($HEIGHT_TT)) {
	$HEIGHT_TT		= TOOL_TIP_HEIGHT;
}
if (!isset($HEIGHT_CHART)) {
	$HEIGHT_CHART	= CHART_HEIGHT;
}
if (!isset($WIDTH_CHART)) {
	$WIDTH_CHART 	= CHART_NARROW;
}
if (!isset($span)) {
	$span 			= 'P2D';
}
if (!isset($TITLE)) {
	$TITLE			= 'Untitled &mdash;';
}

////////////////////////////////////////////////////////////////////////
// Determine whether date is "NOW" or a specific calendar date
$calendar=false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	echo '<!-- POST -->';
	if (isset($_POST['SUBMIT_CALENDAR'])) {
		echo '<!-- SUBMIT_CALENDAR -->';
		$calendar = true;
	}
	if (isset($_POST['SUBMIT_NOW'])) {
		echo '<!-- SUBMIT_NOW -->';
	}
}
if (!$calendar) {
	$_SESSION['MONTH'] = date('m');
	$_SESSION['DAY']   = date('j');
	$_SESSION['YEAR']  = date('Y');
echo "<!-- DEFAULTS $_SESSION[MONTH]-$_SESSION[DAY]-$_SESSION[YEAR] -->";
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
	if (isset($_POST['MONTH'])) {
		$_SESSION['MONTH'] = $_POST['MONTH'];
	}
	else {
		if (!isset($_SESSION['MONTH'])) {
			$_SESSION['MONTH'] = '01';
		}
	}
	echo "<!-- MONTH:$_SESSION[MONTH] -->";
	if (isset($_POST['DAY'])) {
		$_SESSION['DAY'] = (int)$_POST['DAY'];
		if ($_SESSION['DAY'] == 0) {
			$_SESSION['DAY'] = '1';
		}
	}
	else {
		if (!isset($_SESSION['DAY'])) {
			$_SESSION['DAY'] = '1';
		}
	}
	echo "<!-- DAY:$_SESSION[DAY] -->";
	if (isset($_POST['YEAR'])) {
		$_SESSION['YEAR'] = $_POST['YEAR'];
	}
	else {
		if (!isset($_SESSION['YEAR'])) {
			$_SESSION['YEAR'] = '2014';
		}
	}
	echo "<!-- YEAR:$_SESSION[YEAR] -->";
}

////////////////////////////////////////////////////////////////////////
// Visual start-end dates
if ($calendar) {
	$dt = new DateTime("$_SESSION[YEAR]-$_SESSION[MONTH]-$_SESSION[DAY] 00:00:00");
	$dtm2 = new DateTime("$_SESSION[YEAR]-$_SESSION[MONTH]-$_SESSION[DAY] 00:00:00");
	$dtm2->add(new DateInterval($span));
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
		$result = '<!-- Exception: '.$chgx->getMessage()." -->\r\n";
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
    <p style="font-size:22px;"><a href="http://cyberspark.net/"><img src="images/CyberSpark-banner-320x55.png" width="300" height="50" alt="CyberSpark web site"/></a><a href='index.php'><img src="images/uparrow.jpg" width="52" height="48" alt="Analysis home page"/></a>
    
<form id='CS_FORM' action='<?php echo $_SERVER['REQUEST_URI']; ?>' method='post'>
    <select id='MONTH' name='MONTH'>
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
    <input id='DAY' name='DAY' type='text' size='2' <?php if (isset($_SESSION['DAY'])) { echo ' value="'.$_SESSION[DAY].'"'; } ?> />
    <select id='YEAR' name='YEAR'>
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
	</select>
    <input id='SUBMIT_CALENDAR' name='SUBMIT_CALENDAR' type='submit' value='Go to date' />&nbsp;&nbsp;||&nbsp;&nbsp;<input id='SUBMIT_NOW'      name='SUBMIT_NOW'      type='submit' value='Now' />
</form>    
    
    <hr/><span class="CS_TITLE"><? echo $TITLE; ?></span><?php if (!$calendar) { ?><br/><span class="CS_SUBTITLE">This page reloads every few minutes</span><?php } ?>
    </p><hr/>
    <div id="section" style="height:30px; width:<?php echo $WIDTH_CHART; ?>;">
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
		echo "<a href='$sites[$key]' style='text-decoration:none; font-size:12pt;' target='W_$URL_HASH'><svg class='chart' id='H_$URL_HASH' width='$WIDTH_CHART'></svg></a>\r\n";
	}

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
	var titleText = urlFromHash[hash]+" { "+data.length+" samples }";
	// Rectangle behind text
	chart[hash].append("rect")
	    .attr("x", 2)             
	    .attr("y", 2)
 	    .attr("width", (urlFromHash[hash].length*6)+90)  /* use an estimated length */           
	    .attr("height", 17)
		.attr("id", "RT_"+hash)
	    .attr("fill", "white")
	    .attr("fill-opacity", 0.80);
	// Text (URL)
	chart[hash].append("text")
	    .attr("x", 10)             
	    .attr("y", 15)
    	.attr("class", "title")
		.attr("id", "TT_"+hash)
	    .style("font-size", "12px") 
	    .style("font-weight", "bold") 
	    .text(titleText);
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
<hr/>
<!-- legend -->
<table id='LEGEND_NARROW' cellspacing='5' cellpadding='0' border='0' style='width:100%; max-width:<? echo CHART_NARROW; ?>px; font-size:11px;'>
		<tr>
			<td class='rect_green' style='width:20px;'></td>
			<td>&lt;=2 sec
			</td>
			<td class='rect_yellow' style='width:20px;'></td>
			<td>3 or 4
			</td>
			<td class='rect_orange' style='width:20px;'></td>
			<td>5 or 6
			</td	>
			<td class='rect_red' style='width:20px;'></td>
			<td>&gt;6 sec
			</td>
			<td class='rect_blue' style='width:20px;'></td>
			<td>Err
			</td>
			<td class='rect_magenta' style='width:20px;'></td>
			<td>Timeout
			</td>
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
	<table id='LEGEND_WIDE' cellspacing='5' cellpadding='0' border='0' style='width:100%;max-width:<? echo CHART_WIDE; ?>px; font-size:11px;'>
		<tr>
			<td class='rect_green' style='width:20px;'></td>
			<td>Under 2 seconds
			</td>
			<td class='rect_yellow' style='width:20px;'></td>
			<td>3 or 4 seconds
			</td>
			<td class='rect_orange' style='width:20px;'></td>
			<td>5 or 6 seconds
			</td>
			<td class='rect_red' style='width:20px;'></td>
			<td>Over 6 seconds
			</td>
			<td class='rect_blue' style='width:20px;'></td>
			<td>HTTP error
			</td>
			<td class='rect_magenta' style='width:20px;'></td>
			<td>Timeout
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
</body>
</html>