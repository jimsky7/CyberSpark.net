<?php

////////////////////////////////////////////////////////////////////////
// Write arrays for hashes themselves, URLs from hashes, data URLs to fetch data

?>
<!-- common SVG begins -->
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
var CHART_TIMEOUT = 30;		/* this value of http_ms (or higher) signals timeout  */
var CHART_CURL    = 31;     /* this value indicaed a cURL "server failure" error */
var CHART_MAGENTA = CHART_TIMEOUT; /* value of http_ms for timeout warning */
var CHART_RED = CHART_MAX;	/* this is the value of http_ms that means 'off the charts' */
var CHART_ORANGE = 4;		/* above this turns orange */
var CHART_YELLOW = 2;		/* above this turne yellow */
var CHART_GREEN = 0;	

var CURLE_OPERATION_TIMEDOUT = <?php echo CURLE_OPERATION_TIMEDOUT; ?>;
var CURLE_RECV_ERROR         = <?php echo CURLE_RECV_ERROR;         ?>;		

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

////////////////////////////////////////////////////////////////////////
// Begin actual javascript to create the charts
// The real work is done in 'd3.tsv' which reads a file and builds an SVG object
?>
var width  = <?php echo $WIDTH_CHART; ?>; /* default */
var height = <?php echo $HEIGHT_CHART; ?>;

var divTooltip = d3.select("body").append("div")   
    .attr("class",    "tooltip")
	.attr("id",       "DIV_TT")              
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

////////////////////////////////////////////////////////////////////////
// Javascript functions that are used by D3 to build the charts
?>

function csIntensityPlot(error,data,hash) {
	y.domain([-0.20, d3.max(data, function(d) { return CHART_MAX; })]);

	var barWidth = width / data.length;
	var barMargin = 0;
	var bar = chart[hash].selectAll("g")
      .data(data)
      .enter().append("g")
      .attr("transform", function(d, i) { return "translate(" + i * barWidth + ",0)"; });

	bar.append("rect")
      .attr("y",         function(d) { return y(Math.min(CHART_MAX,d.http_ms)); })
      .attr("height",    function(d) { return height - y(Math.min(CHART_MAX,d.http_ms)); })
      .attr("class",     function(d) { 
      		if (d.http_ms>CHART_ALERT) { 
      			return "rect_alert"; 
      		} else { 
      			return (
<?php if (defined('CHART_CURL')) {               ?>     				(d.http_ms==CHART_CURL)?"rect_cyan":	
<?php } ?>
<?php if (defined('CURLE_OPERATION_TIMEDOUT')) { ?>     				(d.result_code==CURLE_OPERATION_TIMEDOUT)?"rect_magenta":	
<?php } ?>
<?php if (defined('CURLE_RECV_ERROR')) {         ?>     				(d.result_code==CURLE_RECV_ERROR)?"rect_magenta":	
<?php }  ?>
     				(d.http_ms>CHART_MAGENTA)?"rect_magenta":	
     				(d.http_ms>CHART_RED)?"rect_red":
      				(d.http_ms>CHART_ORANGE)?"rect_orange":
      				(d.http_ms>CHART_YELLOW)?"rect_yellow":
      				"rect_green"
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
<?php if (defined('CHART_CURL')) {               ?>					(d.http_ms==CHART_CURL)?("{ CONNECT FAILED or REFUSED } &raquo; "):
<?php } ?>
					(d.result_code>CHART_ALERT)?("{ HTTP "+d.result_code+" } &raquo; "):
<?php if (defined('CURLE_OPERATION_TIMEDOUT')) { ?>					(d.result_code==CURLE_OPERATION_TIMEDOUT)?("{ TIMEOUT "+d.http_ms+"s } &raquo; "):
<?php } ?>
<?php if (defined('CURLE_RECV_ERROR')) {         ?>					(d.result_code==CURLE_RECV_ERROR)?("{ SERVER ISSUES } &raquo; "):
<?php } ?>
					(d.http_ms>CHART_TIMEOUT)?("{ TIMEOUT "+d.http_ms+"s } &raquo; "):
            		("{"+d.http_ms+"s} ")
            	)+d.date)  
                .style("left", (((d3.event.pageX+<?php echo $WIDTH_TT; ?>)>window.innerWidth)?(window.innerWidth-<?php echo $WIDTH_TT; ?>):(d3.event.pageX)) + "px")     
                .style("top",  (d3.event.pageY - 28) + "px");    
            })                  
      .on("mouseout", function(d) {       
            divTooltip.transition()        
                .duration(500)      
                .style("opacity", 0);   
        });
		
	// Append a chart title containing the HASH or URL
	var urlText = urlFromHash[hash];
	var titleText = urlText.replace('//www.','//').replace('http://','').replace('https://','').replace(/\/$/,'')+" { "+data.length+" samples }";
	// Rectangle behind text
	chart[hash].append("rect")
	    .attr("x",            12)             
	    .attr("y",            2)
 	    .attr("width",        (urlText.length*6)+60)  /* use an estimated length */           
	    .attr("height",       17)
		.attr("id",           "RT_"+hash)
	    .attr("fill",         "white")
	    .attr("fill-opacity", 0.85);
	// Text (URL)
	chart[hash].append("text")
	    .attr("x",            14)             
	    .attr("y",            15)
    	.attr("class",        "title")
		.attr("id",           "TT_"+hash)
	    .style("font-size",   "12px") 
	    .style("font-weight", "bold") 
	    .text(titleText.replace('//www.','//').replace('http://','').replace('https://','').replace(/\/$/,''));
	// link
	chart[hash].append("a")
     	.attr("target",     "_blank")
    	.attr("xlink:href", urlText)
 		.append("rect")  
    	.attr("x",          0)
    	.attr("y",          0)
    	.attr("height",     8)
    	.attr("width",      8)
    	.style("fill",      "#e0e0e0")	/* 8020A0 */
    	.attr("rx",         1)
    	.attr("ry",         1);

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
	if (d.result_code < 100) {
		/* If result code indicates cURL error, treat it specially */
		d.http_ms = CHART_CURL;
<?php		if (defined('CURLE_OPERATION_TIMEDOUT')) { ?>	if (d.result_code==CURLE_OPERATION_TIMEDOUT) {
			d.http_ms = CHART_TIMEOUT;
		}
	<?php } ?>
<?php 	if (defined('CURLE_RECV_ERROR')) { ?>	if (d.result_code==CURLE_RECV_ERROR) {
			d.http_ms = CHART_TIMEOUT;
		}
	<?php } ?>
	}
	/* Note: if you return null, this data point will be ignored */
	return d;
}
		
</script>
<!-- common SVG ends -->
<?php
// Must leave this file with PHP open
