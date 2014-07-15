<?php
	$URL_HASH = '37a66f4e8898391c3c2210f1097f0b7a';
?>
<html>
<head>
	<!-- D3js version 3.4.8 -->
	<script src="/d3/d3.min.js" charset="utf-8">
    </script>
    <style>

		.chart rect {
			fill: #EED13A;
		}

		.chart text {
		  fill: #C57B03;
		  font: 10px sans-serif;
		  text-anchor: middle;
		}

	</style>
</head>
<body style="padding-left:40px;">
	<p>
&laquo; <a href='/analysis/'>Back</a> 
	</p>
    <p>
Note:    http://slice.red7.com/get-log-entries.php?URL_HASH=<?php echo $URL_HASH; ?>&span=P1W&limit=17
	</p>
    <p id="section">
    </p>
<svg class="chart"></svg>
    
    <script type="text/javascript">
	
	var data = [4, 8, 15, 16, 23, 42];
var CHART_MAX = 6;	/* clip any values larger than this */
var CHART_RED = 4;
var CHART_YELLOW = 2;
var CHART_GREEN = 0;
var CHART_COLOR_RED = "#C00000";
var CHART_COLOR_YELLOW = "#f0f000";
var CHART_COLOR_GREEN = "#00C000";
	
	/* test chart */

var width = 960,
    height = 500;

var y = d3.scale.linear()
    .range([height, 0]);

var chart = d3.select(".chart")
    .attr("width", width)
    .attr("height", height);
	
d3.tsv("/get-log-entries.php?format=tsv&URL_HASH=<?php echo $URL_HASH; ?>&span=P1W", type, function(error, data) {
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
	  .style("fill",   function(d) { return ((d.http_ms>CHART_RED)?CHART_COLOR_RED:((d.http_ms>CHART_YELLOW)?CHART_COLOR_YELLOW:CHART_COLOR_GREEN)); } )
      .attr("width", barWidth - 1);

  bar.append("text")
      .attr("x", barWidth / 2)
      .attr("y", function(d) { return y(Math.min(CHART_MAX,d.http_ms)) + 3; })
      .attr("dy", ".75em")
      .text(function(d) { return d.http_ms; });

});

function type(d) {
  d.http_ms = +d.http_ms; // coerce to number
  /* Note: if you return null, this data point will be ignored */
  return d;
}
		
		
		
		
	</script>

</body>
</html>