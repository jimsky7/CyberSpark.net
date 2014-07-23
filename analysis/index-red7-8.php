<?php
include('cs-log-pw.php');
include('cs-log-config.php');

////////////////////////////////////////////////////////////////////////
// Relied upon D3js tutorials from
//		http://bost.ocks.org/mike/bar/3/

//	$URL_HASH = '37a66f4e8898391c3c2210f1097f0b7a';	/* nice data P1W */
//	$URL_HASH = 'f2b1fddc9e5830c6c3d82ae74ec05819'; /* has 500 errors the entire time
//	$URL_HASH = '7f7424219e18c35bf2569c8dd2c85c02'; /* has one 500 error */
//	$URL_HASH = '059f4730278b0bdacff4c52aa8b04630';
//	$URL_HASH = '6782f1ca83783dbdbc2e188d54593745';
//	$URL_HASH = '6b2192ddc2aee9a3a99ab8c26e94a364'; /* mostly 500 */
//	$URL_HASH = '79346372039750b5aafc783a1cf8702f'; /* REALLY GOOD ONE with a 503 + slowness */
	
$WIDTH_TT    	= 300;
$HEIGHT_TT		= 20;
$WIDTH_CHART 	= 950;
$HEIGHT_CHART	= 30;

$URL_HASHES		= array(
					'a88f27ca8bf9348cce493fd4aafef0e8',	// avaaz.org
					'10b21ef24fde03c3260577743b3dde26', // bolobhi.org
					'3eaf9a25677e8da2c57eec2a2c8a1feb', // carnegielibrary.org
					'308c12cb5a9b3d1be9578444080361ca', // necessarandproportionate.org
					'8d4b1c075f2d6126908bbae47922b9a2', // ila-net.com
					'735e49bc60f5e45a1e6705a2451fa244', // mettacenter.org
					'd65023b92259538d2369b2c876e31394', // privatemanning.org
					'7cccc4f514f29db8c9723cd0104497cd', // restorethe4th.com
					'f0eb7c108adbbc0f113d666b0041d8b2', // http://wiki.15m.cc/wiki/Portada
					'befe55de3f99cae5945b03f87fd54767', // apc.org
					'37fa21533ea9de1e1b4565569538bff6', // freedomnotfear.org
					'1a45797d0341eaa06f475eba43261cd5', // irex.org
					'9d3ff4a865aac42f230c467200495546', // meta-activism.org
					'18f534fe420b24dede2935a76da9f052', // openrightsgroup.org
					'70fc1509399d3a5e0122ee85c9b2a66a', // theelders.org
					'29947a4ad95e365778eaaacd731d6ff3', // onlinecensorship.org
					'6031d39bab652bf1df49d849ae6be59b', // optin.stopwatching.us
					'2c8c34a61c2d8b1d932c11de8559eada', // secure.avaaz.org/en/
					'4fd9c9a2de4fbab8494b2f27d37f3118', // https://twitter.com
					'e203e98e4c606735cf56db84a002fd22', // https://facebook.com
					'd75277cdffef995a46ae59bdaef1db86', // https://google.com
					'4a1def3e9ff704e9fefbc0415f05237a' // thunderclap.it
//					'fdbe6d429307d63108491564cd5ce0cb' // red7.com
			);
$URLS 			= array();
$getDataURL		= array();
$sites			= array();
$span 			= 'P8D';

// Set up dates+times based on span or explicit dates given
$startTimestamp = new DateTime();

$startDate = '';
$endDate   = '';
$startTimestamp =  0;
$endTimestamp   =  0;

$dt = new DateTime;
$endTimestamp = ((int)$dt->format('U'))*1000;
$endDate = $dt->format('d-M-Y H:i');
//	echo "End: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";
$dt->sub(new DateInterval($span));
$startTimestamp = ((int)$dt->format('U'))*1000;
$startDate = $dt->format('d-M-Y H:i');
//	echo "Start: " . $dt->format('Y-m-d H:i:s') . "<br/>\r\n";

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

?>
<html>
<head>
	<!-- D3js version 3.4.8 is being used -->
	<script src="/d3/d3.min.js" charset="utf-8"></script>
    <meta charset='utf-8' />
    <link href="/css/d3.css" media="all" rel="stylesheet" type="text/css" /> 
	<!-- refresh page every 5 minutes -->
	<meta http-equiv="refresh" content="300; url=<?php echo $_SERVER['REQUEST_URI']; ?>">
    <title>CS9 (dev system) sites</title>
</head>
<body style="padding-left:40px;">
	<p>
&laquo; <a href='/analysis/'>Back</a> 
	</p>
    <p style="font-size:22px;">CS9 (dev system) sites — all CS9 data are “real time”
    </p>
    <div id="section" style="height:30px; width:<?php echo $WIDTH_CHART; ?>;">
    <div style="float:left;">&darr;&nbsp;&nbsp;<?php echo $startDate; ?></div>
    <div style="float:right;"><?php echo $endDate; ?>&nbsp;&nbsp;&darr;</div>
    </div>

<?php
////////////////////////////////////////////////////////////////////////
// Write the SVG objects

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
		$getDataURL[$key]	= CS_URL_GET."?format=tsv&URL_HASH=$URL_HASH&span=$span&pad=true";
		echo "<svg class='chart' id='H_$URL_HASH'></svg>\r\n";
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
var CHART_RED = CHART_MAX;	/* this is the value of http_ms that means 'off the charts' */
var CHART_ORANGE = 4;		/* above this turns orange */
var CHART_YELLOW = 2;		/* above this turne yellow */
var CHART_GREEN = 0;			

<?php
////////////////////////////////////////////////////////////////////////
// Begin actual javascript to create the charts
// The real work is done in 'd3.tsv' which reads a file and builds an SVG object
?>
var width  = <?php echo $WIDTH_CHART; ?>,
    height = <?php echo $HEIGHT_CHART; ?>;

var divTooltip = d3.select("body").append("div")   
    .attr("class", "tooltip")
	.attr("id", "DIV_TT")              
	.style("opacity", 0);

var ix=0;
var chart = [];

for (ix=0; ix<hashes.length; ix++) {
	
	var y = d3.scale.linear()
	    .range([height, 0]);

	var x = d3.scale.ordinal()
	    .rangeRoundBands([0, width], .1);
	
	chart[hashes[ix]] = d3.selectAll("#H_"+hashes[ix])
		.attr("width", width)
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
//	y.domain([0, d3.max(data, function(d) { return Math.min(CHART_MAX,d.http_ms); })]);
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
      .attr("y", function(d) { return y(d.http_ms); })
      .attr("height", function(d) { return height - y(d.http_ms); })
      .attr("class", function(d) { if (d.http_ms>CHART_ALERT) { return "rect_alert"; } else { return ((d.http_ms>CHART_RED)?"rect_red":((d.http_ms>CHART_ORANGE)?"rect_orange":((d.http_ms>CHART_YELLOW)?"rect_yellow":"rect_green"))); } })
	  .attr("width", function(d) { hash = d.URL_HASH; return (barWidth - barMargin); })	/* just a hack to get the hash */
	  .attr("width", barWidth - barMargin)
      .on("mouseover", function(d) {      
            divTooltip.transition()        
                .duration(200)      
                .style("opacity", .80);      
            divTooltip .html(((d.result_code>CHART_ALERT)?('{ HTTP '+d.result_code+' } &raquo; '):('{'+d.http_ms+'s} '))+d.date)  
                .style("left", (((d3.event.pageX+<?php echo $WIDTH_TT; ?>)><?php echo $WIDTH_CHART; ?>)?(<?php echo $WIDTH_CHART; ?>-<?php echo $WIDTH_TT; ?>):d3.event.pageX) + "px")     
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
</body>
</html>