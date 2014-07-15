<?php

////////////////////////////////////////////////////////////////////////
// http://bost.ocks.org/mike/bar/3/

	$URL_HASH = '37a66f4e8898391c3c2210f1097f0b7a';	/* nice data P1W */
		$span = 'P1W';
//	$URL_HASH = 'f2b1fddc9e5830c6c3d82ae74ec05819'; /* has 500 errors the entire time */
//	$URL_HASH = '7f7424219e18c35bf2569c8dd2c85c02'; /* has one 500 error */
//	$URL_HASH = '059f4730278b0bdacff4c52aa8b04630';
	$URL_HASH = '6782f1ca83783dbdbc2e188d54593745';
	$URL_HASH = '6b2192ddc2aee9a3a99ab8c26e94a364'; /* mostly 500 */
	$URL_HASH = '79346372039750b5aafc783a1cf8702f'; /* REALLY GOOD ONE with a 503 + slowness */
	
	$URL = '/get-log-entries.php?format=tsv&URL_HASH='.$URL_HASH.'&span='.$span.'&limit=0';
	
?>
<html>
<head>
	<!-- D3js version 3.4.8 -->
	<script src="/d3/d3.min.js" charset="utf-8">
    </script>
    <style>

		.chart rect {
			fill: #EED13A;	/* just a default */
		}

		.chart text {
		  fill: #C57B03;
		  font: 10px sans-serif;
		  text-anchor: middle;
		}
		
		.axis text {
			font: 10px sans-serif;
		}

		.axis path,
		.axis line {
			fill: none;
			stroke: #000;
			shape-rendering: crispEdges;
		}
		.x_axis {
			margin-top:4px;
		}
		
		.rect_alert {
			fill: #808080 !important; /* override background */
		}
		.rect_red {
			fill: #c00000 !important; /* override background */
		}
		.rect_orange {
			fill: #f08000 !important; /* override background */
		}
		.rect_yellow {
			fill: #f0f000 !important; /* override background */
		}
		.rect_green {
			fill: #00c000 !important; /* override background */
		}
	</style>
</head>
<body style="padding-left:40px;">
	<p>
&laquo; <a href='/analysis/'>Back</a> 
	</p>
    <p>
Note:    <?php echo $URL; ?>
	</p>
    <p id="section">
    </p>
<svg class="chart"></svg>
    
    <script type="text/javascript">
	
	var data = [0];
var CHART_MAX = 6;	/* clip any values larger than this */
var CHART_ALERT = 300;	/* this value, or higher, signals an alert */
var CHART_RED = CHART_MAX;
var CHART_ORANGE = 4;
var CHART_YELLOW = 2;
var CHART_GREEN = 0;
	
	/* test chart */

var width = 960,
    height = 500;

var y = d3.scale.linear()
    .range([height, 0]);

var x = d3.scale.ordinal()
    .rangeRoundBands([0, width], .1);
	
var chart = d3.select(".chart")
    .attr("width", width)
    .attr("height", height);
	
d3.tsv("<?php echo $URL; ?>", type, function(error, data) {
  y.domain([0, d3.max(data, function(d) { return Math.min(CHART_MAX,d.http_ms); })]);

	var section = d3.selectAll("body");
	var div = section.append("div");
	div.html("<br/><br/>There are "+data.length+" data points.");
	div.style("color","black");
	div.style("font-weight","bold");

  var barWidth = width / data.length;

  var bar = chart.selectAll("g")
      .data(data)
    .enter().append("g")
      .attr("transform", function(d, i) { return "translate(" + i * barWidth + ",0)"; });

   bar.append("rect")
      .attr("y", function(d) { return y(d.http_ms); })
      .attr("height", function(d) { return height - y(d.http_ms); })
      .attr("class", function(d) { if (d.http_ms>CHART_ALERT) { return "rect_alert"; } else { return ((d.http_ms>CHART_RED)?"rect_red":((d.http_ms>CHART_ORANGE)?"rect_orange":((d.http_ms>CHART_YELLOW)?"rect_yellow":"rect_green"))); } })
	  .attr("width", barWidth - 1);

  bar.append("text")
      .attr("x", barWidth / 2)
      .attr("y", function(d) { return y(Math.min(CHART_MAX,d.http_ms)) + 3; })
      .attr("dy", ".75em")
      .text(function(d) { return d.http_ms; });

var xAxis = d3.svg.axis()
    .scale(x)
    .orient("bottom");
	
var yAxis = d3.svg.axis()
    .scale(y)
    .orient("left")
    .ticks(10, "%");
	
chart.append("g")
    .attr("class", "x_axis")
    .attr("transform", "translate(0," + height + ")")
    .call(xAxis)
	.append("text")
    	.attr("transform", "rotate(-90)")
    	.attr("y", 6)
		.attr("dy", ".71em")
    	.style("text-anchor", "end")
		.text("time");
	
});

function type(d) {
  d.http_ms = +d.http_ms; // coerce to number
  if (d.result_code > 299) {
	  /* If result code indicates HTTP error, plug it in as `http_ms` for later action */
	  d.http_ms = +d.result_code;
  }
  /* Note: if you return null, this data point will be ignored */
  return d;
}
		
		
		
		
	</script>

</body>
</html>