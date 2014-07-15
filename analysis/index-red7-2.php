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
		  text-anchor: end;
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
	
	/* test chart */

var width = 420,
    barHeight = 20;

var x = d3.scale.linear()
    .range([0, width]);

var chart = d3.select(".chart")
    .attr("width", width);

d3.tsv("/get-log-entries.php?format=tsv&URL_HASH=<?php echo $URL_HASH; ?>&span=P1W&limit=10", type, function(error, data) {
  x.domain([0, d3.max(data, function(d) { return d.http_ms; })]);

  chart.attr("height", barHeight * data.length);

  var bar = chart.selectAll("g")
      .data(data)
    .enter().append("g")
      .attr("transform", function(d, i) { return "translate(0," + i * barHeight + ")"; });

  bar.append("rect")
      .attr("width", function(d) { return x(d.http_ms); })
      .attr("height", barHeight - 1);

  bar.append("text")
      .attr("x", function(d) { if (d.http_ms==0) { return x(0) + 13; } else { return x(d.http_ms) -3;} })
      .attr("y", barHeight / 2)
      .attr("dy", ".35em")
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