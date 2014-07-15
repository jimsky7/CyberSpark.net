<?php
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
					'ab0d9abedb77357a563dd1339d51a516',	// msf.org
					'9048cfcacb30d9c2781959b94313cdcd', // nobelprize.org
					'79346372039750b5aafc783a1cf8702f', // globalforceforhealing.org
					'0916ea889a723c2f691b5a12c175fd09', // humanityunited.org
					'a4a03119d94266896e70fb9d4fb5f3c4', // alkasir.com
//					'fdbe6d429307d63108491564cd5ce0cb', // red7.com
					'383f65f95363c204221bd6b4cc4d6701', // en.rsf.org
					'a88f27ca8bf9348cce493fd4aafef0e8', // avaaz.org
					'8e7e3b9ec73001a8035360fbd966ad91', // accessnow.org
					'2a49903efca953e340a5a31748f342eb', // theforgotten.org
					'ba5e509c82ebbab6be69a00fb47be270', // chinese-leaders.org
					'37a66f4e8898391c3c2210f1097f0b7a', // www.codethechange.org
//					'6ab5eaca112b4186de8b0102d5e7332e', //chokepointproject.net
					'd18b5c4405eab654f212216a8650782f', // publeaks.nl
					'a4ecd3013c8306027453b05779051210', // http://www.civilrightsdefenders.org/ { 156 samples }
					'8dd9360ab6cfe963fd05360ac82e379e', // http://www.fidh.org/ { 157 samples }
//					'b3eaf4bce2b85708a6c930a5d527340d', // cyberspark.net
					'b622717f93236090dc6f117f866904b6'  // chinachange.org
			);
$URLS 			= array();
$getDataURL		= array();
$sites			= array();
$span 			= 'P5D';

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

?>
<html>
<head>
	<!-- D3js version 3.4.8 is being used -->
	<script src="/d3/d3.min.js" charset="utf-8"></script>
    <link href="/css/d3.css" media="all" rel="stylesheet" type="text/css" />   
</head>
<body style="padding-left:40px;">
	<p>
&laquo; <a href='/analysis/'>Back</a> 
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
//	echo "<!-- getHashURL: $getHashURL -->\r\n";
	foreach ($URL_HASHES as $key=>$URL_HASH) {
		// http://example.com/analysis/cs-log-get-url-from-hash.php?URL_HASH=ab0d9abedb77357a563dd1339d51a516
		$sites[$key]			= file_get_contents($getHashURL."?URL_HASH=$URL_HASH");
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
	var bar = chart[hash].selectAll("g")
      .data(data)
    .enter().append("g")
      .attr("transform", function(d, i) { return "translate(" + i * barWidth + ",0)"; });

	bar.append("rect")
      .attr("y", function(d) { return y(d.http_ms); })
      .attr("height", function(d) { return height - y(d.http_ms); })
      .attr("class", function(d) { if (d.http_ms>CHART_ALERT) { return "rect_alert"; } else { return ((d.http_ms>CHART_RED)?"rect_red":((d.http_ms>CHART_ORANGE)?"rect_orange":((d.http_ms>CHART_YELLOW)?"rect_yellow":"rect_green"))); } })
	  .attr("width", function(d) { hash = d.URL_HASH; return (barWidth - 1); })	/* just a hack to get the hash */
	  .attr("width", barWidth - 1)
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